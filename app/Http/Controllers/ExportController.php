<?php

namespace App\Http\Controllers;

use App\Models\HarvestLog;
use App\Models\SensorData;
use App\Services\MushroomSpeciesCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    private function buildSensorQuery(Request $request): Builder
    {
        $boxId = $request->query('box_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = SensorData::orderBy('recorded_at', 'desc');

        if ($boxId && $boxId !== 'all') {
            if ($boxId === 'box_a') {
                $query->where(function ($q) {
                    $q->where('box_id', 'box_a')->orWhereNull('box_id');
                });
            } else {
                $query->where('box_id', $boxId);
            }
        }

        if ($startDate) {
            $query->where('recorded_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $query->where('recorded_at', '<=', $endDate . ' 23:59:59');
        }

        return $query;
    }

    private function buildHarvestQuery(Request $request): Builder
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = HarvestLog::orderBy('harvested_at', 'desc');

        if ($startDate) {
            $query->where('harvested_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $query->where('harvested_at', '<=', $endDate . ' 23:59:59');
        }

        return $query;
    }

    private function filterLabel(Request $request, bool $includeBox = false): string
    {
        $parts = [];
        if ($includeBox) {
            $box = $request->query('box_id', 'all');
            $parts[] = 'Box: ' . ($box === 'all' ? 'All' : strtoupper(str_replace('_', ' ', $box)));
        }
        $start = $request->query('start_date');
        $end = $request->query('end_date');
        $parts[] = ($start || $end)
            ? 'Date: ' . ($start ?: 'Start') . ' to ' . ($end ?: 'Present')
            : 'Date: All Records';

        return implode(' | ', $parts);
    }

    private function streamSpreadsheet(string $title, array $headers, array $rows, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($title, 0, 31));

        $sheet->fromArray($headers, null, 'A1');
        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '065F46']],
        ]);

        for ($i = 1; $i <= count($headers); $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Export sensor telemetry data as CSV */
    public function exportSensorData(Request $request): StreamedResponse
    {
        $query = $this->buildSensorQuery($request);
        $filename = 'kabutech_sensor_data_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Box ID',
                'Temperature (°C)',
                'Humidity (%)',
                'Wi-Fi RSSI (dBm)',
                'Misting Active',
                'Misting Source',
                'Misting Reason',
                'Total Misting Duration (ms)',
                'Recorded At'
            ]);

            $query->chunk(1000, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->box_id ?? 'box_a',
                        $row->temperature,
                        $row->humidity,
                        $row->wifi_rssi,
                        $row->misting_system ? 'YES' : 'NO',
                        $row->misting_source,
                        $row->misting_reason,
                        $row->misting_total_ms,
                        $row->recorded_at ? $row->recorded_at->timezone('Asia/Manila')->toDateTimeString() : ''
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Export sensor telemetry data as Excel (.xlsx) via PhpSpreadsheet */
    public function exportSensorExcel(Request $request): StreamedResponse
    {
        $records = $this->buildSensorQuery($request)->limit(2000)->get();

        $headers = ['ID', 'Box', 'Temperature (°C)', 'Humidity (%)', 'Misting', 'Fan', 'Mode', 'Wi-Fi RSSI', 'Recorded At (PH)'];
        $rows = $records->map(fn ($r) => [
            $r->id,
            $r->box_id ?? 'box_a',
            $r->temperature !== null ? round((float) $r->temperature, 2) : null,
            $r->humidity !== null ? round((float) $r->humidity, 2) : null,
            $r->misting_system ? 'ON' : 'OFF',
            $r->fan_system ? 'ON' : 'OFF',
            $r->misting_source ?? 'auto',
            $r->wifi_rssi,
            $r->recorded_at ? $r->recorded_at->timezone('Asia/Manila')->format('Y-m-d H:i:s') : '',
        ])->all();

        return $this->streamSpreadsheet(
            'Sensor Telemetry',
            $headers,
            $rows,
            'kabutech_sensor_report_' . date('Ymd_His') . '.xlsx'
        );
    }

    /** Export sensor telemetry report as PDF via DomPDF */
    public function exportSensorPdf(Request $request)
    {
        $records = $this->buildSensorQuery($request)->limit(200)->get();

        $avgTemp = $records->whereNotNull('temperature')->avg('temperature');
        $avgHum  = $records->whereNotNull('humidity')->avg('humidity');
        $minTemp = $records->whereNotNull('temperature')->min('temperature');
        $maxTemp = $records->whereNotNull('temperature')->max('temperature');

        $summaryCards = [
            ['label' => 'Records Included', 'value' => (string) $records->count()],
            ['label' => 'Avg Temperature', 'value' => $avgTemp !== null ? number_format((float) $avgTemp, 1) . ' °C' : '--'],
            ['label' => 'Avg Humidity', 'value' => $avgHum !== null ? number_format((float) $avgHum, 1) . ' %' : '--'],
            ['label' => 'Temp Min / Max', 'value' => ($minTemp !== null && $maxTemp !== null) ? number_format((float) $minTemp, 1) . ' / ' . number_format((float) $maxTemp, 1) . ' °C' : '--'],
        ];

        $columns = ['Time (PH)', 'Box', 'Temp (°C)', 'Humidity (%)', 'Misting', 'Fan'];
        $rows = $records->map(fn ($r) => [
            $r->recorded_at ? $r->recorded_at->timezone('Asia/Manila')->format('M d, Y g:i A') : '--',
            strtoupper(str_replace('_', ' ', $r->box_id ?? 'box_a')),
            $r->temperature !== null ? number_format((float) $r->temperature, 1) : '--',
            $r->humidity !== null ? number_format((float) $r->humidity, 1) : '--',
            $r->misting_system ? 'ON' : 'OFF',
            $r->fan_system ? 'ON' : 'OFF',
        ])->all();

        $pdf = Pdf::loadView('reports.pdf', [
            'title'        => 'Sensor Telemetry Report',
            'generatedAt'  => now('Asia/Manila')->format('M d, Y g:i A'),
            'filterLabel'  => $this->filterLabel($request, true),
            'summaryCards' => $summaryCards,
            'columns'      => $columns,
            'rows'         => $rows,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('kabutech_sensor_report_' . date('Ymd_His') . '.pdf');
    }

    /** Export harvest yield logs as CSV */
    public function exportHarvestLogs(Request $request): StreamedResponse
    {
        $query = $this->buildHarvestQuery($request);
        $filename = 'kabutech_harvest_logs_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Batch ID / Code',
                'Batch Name',
                'Mushroom Species',
                'Incubator Box',
                'Flush Number',
                'Harvest Weight (g)',
                'Substrate Weight (g)',
                'Biological Efficiency (%)',
                'Batch Avg Temp (°C)',
                'Batch Avg Humidity (%)',
                'Best Temp Target (°C)',
                'Best Humidity Target (%)',
                'Notes',
                'Harvested At'
            ]);

            $query->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    $profile = MushroomSpeciesCatalog::profile($row->mushroom_type)
                        ?? MushroomSpeciesCatalog::profile(MushroomSpeciesCatalog::defaultKey());
                    $bestTempStr = $profile ? "{$profile['temp_min']} - {$profile['temp_max']} °C" : '20 - 26 °C';
                    $bestHumStr  = $profile ? "{$profile['hum_min']} - {$profile['hum_max']} %" : '85 - 95 %';

                    fputcsv($handle, [
                        $row->id,
                        $row->batch_id,
                        $row->batch_name,
                        $row->mushroom_type,
                        $row->incubator,
                        $row->flush_number,
                        $row->weight_grams,
                        $row->substrate_weight_grams,
                        $row->biological_efficiency,
                        $row->temperature !== null ? $row->temperature : '--',
                        $row->humidity !== null ? $row->humidity : '--',
                        $bestTempStr,
                        $bestHumStr,
                        $row->notes,
                        $row->harvested_at ? $row->harvested_at : ''
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Export harvest yield logs as Excel (.xlsx) via PhpSpreadsheet */
    public function exportHarvestExcel(Request $request): StreamedResponse
    {
        $records = $this->buildHarvestQuery($request)->get();

        $headers = ['ID', 'Batch Name', 'Species', 'Box', 'Flush #', 'Weight (g)', 'Substrate (g)', 'BE (%)', 'Avg Temp (°C)', 'Avg Hum (%)', 'Quality', 'Harvested At'];
        $rows = $records->map(fn ($r) => [
            $r->id,
            $r->batch_name,
            MushroomSpeciesCatalog::profile($r->mushroom_type)['label'] ?? $r->mushroom_type,
            $r->incubator ?? 'box_a',
            $r->flush_number,
            round((float) $r->weight_grams, 2),
            $r->substrate_weight_grams !== null ? round((float) $r->substrate_weight_grams, 2) : null,
            $r->biological_efficiency !== null ? round((float) $r->biological_efficiency, 2) : null,
            $r->temperature !== null ? round((float) $r->temperature, 1) : null,
            $r->humidity !== null ? round((float) $r->humidity, 1) : null,
            $r->quality ?? 'Grade A',
            $r->harvested_at ? $r->harvested_at->format('Y-m-d') : '',
        ])->all();

        return $this->streamSpreadsheet(
            'Harvest Yield Logs',
            $headers,
            $rows,
            'kabutech_harvest_report_' . date('Ymd_His') . '.xlsx'
        );
    }

    /** Export harvest yield report as PDF via DomPDF */
    public function exportHarvestPdf(Request $request)
    {
        $records = $this->buildHarvestQuery($request)->get();

        $totalGrams = (float) $records->sum('weight_grams');
        $avgBe = $records->whereNotNull('biological_efficiency')->avg('biological_efficiency');

        $summaryCards = [
            ['label' => 'Total Flushes', 'value' => (string) $records->count()],
            ['label' => 'Total Yield', 'value' => number_format($totalGrams / 1000, 2) . ' kg (' . number_format($totalGrams, 0) . ' g)'],
            ['label' => 'Avg BE %', 'value' => $avgBe !== null ? number_format((float) $avgBe, 1) . '%' : '--'],
            ['label' => 'Species', 'value' => 'Oyster (White & Brown)'],
        ];

        $columns = ['Date', 'Batch Name', 'Species', 'Flush', 'Weight (g)', 'BE %', 'Climate'];
        $rows = $records->map(fn ($r) => [
            $r->harvested_at ? $r->harvested_at->format('M d, Y') : '--',
            $r->batch_name,
            MushroomSpeciesCatalog::profile($r->mushroom_type)['label'] ?? $r->mushroom_type,
            '#' . $r->flush_number,
            number_format((float) $r->weight_grams, 1),
            $r->biological_efficiency !== null ? number_format((float) $r->biological_efficiency, 1) . '%' : '--',
            ($r->temperature !== null ? number_format((float) $r->temperature, 1) . '°C' : '--') . ' / ' . ($r->humidity !== null ? number_format((float) $r->humidity, 0) . '%' : '--'),
        ])->all();

        $pdf = Pdf::loadView('reports.pdf', [
            'title'        => 'Harvest Yield Report',
            'generatedAt'  => now('Asia/Manila')->format('M d, Y g:i A'),
            'filterLabel'  => $this->filterLabel($request, false),
            'summaryCards' => $summaryCards,
            'columns'      => $columns,
            'rows'         => $rows,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('kabutech_harvest_report_' . date('Ymd_His') . '.pdf');
    }

    /** Get yield vs environmental correlation analysis */
    public function getCorrelationAnalytics(Request $request): JsonResponse
    {
        $harvests = HarvestLog::orderBy('harvested_at', 'asc')->get();

        $correlationData = $harvests->map(function ($harvest) {
            $harvestDate = \Carbon\Carbon::parse($harvest->harvested_at);
            $windowStart = $harvestDate->copy()->subDays(7);

            $boxId = $harvest->incubator ?? 'box_a';
            $avgQuery = SensorData::whereBetween('recorded_at', [$windowStart, $harvestDate]);

            if ($boxId === 'box_a') {
                $avgQuery->where(function ($q) {
                    $q->where('box_id', 'box_a')->orWhereNull('box_id');
                });
            } else {
                $avgQuery->where('box_id', $boxId);
            }

            $avgTemp = $harvest->temperature !== null
                ? round((float) $harvest->temperature, 1)
                : round((float) ($avgQuery->avg('temperature') ?? 24.0), 1);
            $avgHum  = $harvest->humidity !== null
                ? round((float) $harvest->humidity, 1)
                : round((float) ($avgQuery->avg('humidity') ?? 88.0), 1);

            return [
                'harvest_id'      => $harvest->id,
                'batch_name'      => $harvest->batch_name,
                'mushroom_type'   => $harvest->mushroom_type,
                'flush_number'    => $harvest->flush_number,
                'weight_grams'    => (float) $harvest->weight_grams,
                'temperature'     => $avgTemp,
                'humidity'        => $avgHum,
                'harvested_at'    => $harvestDate->toDateString(),
                'avg_temp_7d'     => $avgTemp,
                'avg_hum_7d'      => $avgHum,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $correlationData,
        ]);
    }
}
