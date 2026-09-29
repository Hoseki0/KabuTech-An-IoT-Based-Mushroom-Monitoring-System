<?php

namespace App\Http\Controllers;

use App\Models\HarvestLog;
use App\Models\SensorData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /** Export sensor telemetry data as CSV */
    public function exportSensorData(Request $request): StreamedResponse
    {
        $boxId = $request->query('box_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = SensorData::orderBy('recorded_at', 'asc');

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
                        $row->recorded_at ? $row->recorded_at->toDateTimeString() : ''
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Export harvest yield logs as CSV */
    public function exportHarvestLogs(Request $request): StreamedResponse
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
                'Quality Grade',
                'Notes',
                'Harvested At'
            ]);

            $query->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
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
                        $row->quality,
                        $row->notes,
                        $row->harvested_at ? $row->harvested_at : ''
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
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

            $avgTemp = round($avgQuery->avg('temperature') ?? 0, 2);
            $avgHum  = round($avgQuery->avg('humidity') ?? 0, 2);

            return [
                'harvest_id'      => $harvest->id,
                'batch_name'      => $harvest->batch_name,
                'mushroom_type'   => $harvest->mushroom_type,
                'flush_number'    => $harvest->flush_number,
                'weight_grams'    => (float) $harvest->weight_grams,
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
