<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GrowSetting;
use App\Models\SensorData;
use App\Services\GrowAlertService;
use App\Services\MushroomSpeciesCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IoTController extends Controller
{
    const KNOWN_BOXES = ['box_a', 'box_b', 'box_c'];

    public function getLatest(Request $request): JsonResponse
    {
        $boxId = $request->query('box');
        $query = SensorData::latest('recorded_at');
        if ($boxId) {
            if ($boxId === 'box_a') {
                $query->where(function ($q) { $q->where('box_id', 'box_a')->orWhereNull('box_id'); });
            } else {
                $query->where('box_id', $boxId);
            }
        }
        $latest = $query->first();
        if (! $latest) {
            return response()->json(['box_id' => $boxId ?? null, 'temperature' => null, 'humidity' => null, 'misting_system' => false, 'recorded_at' => null])
                ->header('Cache-Control', 'no-store');
        }
        return response()->json($this->formatRow($latest))
            ->header('Cache-Control', 'private, max-age=3');
    }

    public function getBoxesLatest(): JsonResponse
    {
        // Single query: for each box_id group, grab the row with the max recorded_at.
        // This replaces the previous N-query loop.
        $subquery = SensorData::selectRaw('COALESCE(box_id, \'box_a\') as resolved_box, MAX(recorded_at) as max_at')
            ->groupByRaw('COALESCE(box_id, \'box_a\')');

        $rows = SensorData::joinSub($subquery, 'latest', function ($join) {
                $join->whereRaw('COALESCE(sensor_data.box_id, \'box_a\') = latest.resolved_box')
                     ->on('sensor_data.recorded_at', '=', 'latest.max_at');
            })
            ->get()
            ->keyBy(fn ($r) => $r->box_id ?? 'box_a');

        $result = [];
        $staleThreshold = 120; // seconds

        foreach (self::KNOWN_BOXES as $boxId) {
            if (isset($rows[$boxId])) {
                $row  = $rows[$boxId];
                $data = $this->formatRow($row);
                $stale = $row->recorded_at && now()->diffInSeconds($row->recorded_at) > $staleThreshold;
                $data['connected'] = !$stale;
                if ($stale) $data['stale'] = true;
            } else {
                $data = ['box_id' => $boxId, 'temperature' => null, 'humidity' => null, 'wifi_rssi' => null,
                         'misting_system' => false, 'misting_source' => null, 'misting_reason' => null,
                         'recorded_at' => null, 'connected' => false];
            }
            $result[$boxId] = $data;
        }

        return response()->json($result)
            ->header('Cache-Control', 'private, max-age=3');
    }

    public function receiveData(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'box_id'               => 'nullable|string|max:32',
                'temperature'          => 'nullable|numeric|between:-50,100',
                'humidity'             => 'nullable|numeric|between:0,100',
                'misting_system'       => 'nullable|boolean',
                'wifi_rssi'            => 'nullable|integer|between:-120,0',
                'misting_source'       => 'nullable|string|in:auto,manual',
                'misting_reason'       => 'nullable|string|max:32',
                'misting_total_ms'     => 'nullable|integer|min:0',
                'misting_last_burst_ms'=> 'nullable|integer|min:0|max:600000',
                'fan_system'           => 'nullable|boolean',
            ]);
            $sensorData = SensorData::create([
                'box_id'               => $validated['box_id'] ?? null,
                'temperature'          => $validated['temperature'] ?? null,
                'humidity'             => $validated['humidity'] ?? null,
                'wifi_rssi'            => $validated['wifi_rssi'] ?? null,
                'misting_system'       => $validated['misting_system'] ?? false,
                'misting_source'       => $validated['misting_source'] ?? null,
                'misting_reason'       => $validated['misting_reason'] ?? null,
                'misting_total_ms'     => $validated['misting_total_ms'] ?? null,
                'misting_last_burst_ms'=> $validated['misting_last_burst_ms'] ?? null,
                'fan_system'           => $validated['fan_system'] ?? false,
                'recorded_at'          => now(),
            ]);
            try { app(GrowAlertService::class)->evaluateAfterReading($sensorData); } catch (\Throwable $e) { \Log::warning('Grow alert evaluation failed', ['message' => $e->getMessage()]); }
            try {
                if (\Illuminate\Support\Facades\Cache::add('device_health:last_ingest_check', true, 30)) {
                    app(\App\Services\DeviceHealthService::class)->check(includeCamera: false);
                }
            } catch (\Throwable $e) {
                \Log::warning('Device health evaluation failed', ['message' => $e->getMessage()]);
            }
            return response()->json(['success' => true, 'message' => 'Sensor data received', 'data' => $sensorData], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Sensor data validation failed', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('Sensor data save failed', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error saving sensor data'], 500);
        }
    }

    public function controlMisting(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'  => 'nullable|boolean',
            'mode'    => 'nullable|string|in:auto,manual',
            'profile' => 'nullable|string|in:incubation,fruiting',
        ]);

        $row = DB::table('misting_control')->where('id', 1)->first();
        $mode = $validated['mode'] ?? ($row && $row->desired_mode ? (string) $row->desired_mode : 'manual');
        $profile = $validated['profile'] ?? ($row && $row->desired_profile ? (string) $row->desired_profile : 'fruiting');

        if ($mode === 'auto') {
            $desiredOn = false;
        } elseif (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $desiredOn = (bool) $validated['status'];
        } else {
            $desiredOn = $row ? (bool) $row->desired_on : false;
        }

        DB::table('misting_control')->updateOrInsert(
            ['id' => 1],
            [
                'desired_on'      => $desiredOn,
                'desired_mode'    => $mode,
                'desired_profile' => $profile,
                'updated_at'      => now(),
                'created_at'      => now(),
            ]
        );

        if (isset($validated['mode']) && (!isset($validated['status']) || $mode === 'auto')) {
            $message = $mode === 'auto'
                ? 'Misting mode switched to Automatic (sensor-driven)'
                : 'Misting mode switched to Manual';
        } else {
            $message = 'Misting system ' . ($desiredOn ? 'activated' : 'deactivated');
        }

        return response()->json([
            'success'         => true,
            'message'         => $message,
            'desired_on'      => $desiredOn,
            'desired_mode'    => $mode,
            'desired_profile' => $profile,
        ]);
    }

    public function getMistingStatus(Request $request): JsonResponse
    {
        $row = DB::table('misting_control')->where('id', 1)->first();
        $grow = GrowSetting::singleton();
        $key  = MushroomSpeciesCatalog::isValidKey($grow->mushroom_type) ? $grow->mushroom_type : MushroomSpeciesCatalog::defaultKey();
        $p    = MushroomSpeciesCatalog::profile($key);
        $desiredProfile = $row && $row->desired_profile ? (string) $row->desired_profile : 'fruiting';
        $targets = null;
        if ($p) {
            if ($desiredProfile === 'incubation') {
                $targets = ['temp_min' => isset($p['incubation_temp_min']) ? (float) $p['incubation_temp_min'] : null, 'temp_max' => isset($p['incubation_temp_max']) ? (float) $p['incubation_temp_max'] : null, 'hum_min' => isset($p['incubation_hum_min']) ? (float) $p['incubation_hum_min'] : null, 'hum_max' => isset($p['incubation_hum_max']) ? (float) $p['incubation_hum_max'] : null];
            } else {
                $targets = ['temp_min' => (float) $p['temp_min'], 'temp_max' => (float) $p['temp_max'], 'hum_min' => (float) $p['hum_min'], 'hum_max' => (float) $p['hum_max']];
            }
        }
        // Fan status — read from fan_control table
        $fanRow = DB::table('fan_control')->where('id', 1)->first();

        $desiredMode = $row && $row->desired_mode ? (string) $row->desired_mode : 'auto';
        $desiredOn   = $row ? (bool) $row->desired_on : false;
        $isEsp32     = str_contains((string) $request->userAgent(), 'ESP32');

        // If switching from Manual ON to Auto, send 1 manual-off pulse to ESP32 so it clears mistingStatus=true (mistUntilMs=0)
        if ($isEsp32 && $desiredMode === 'auto') {
            $latest = SensorData::latest('recorded_at')->first(['misting_system', 'misting_source']);
            if ($latest && $latest->misting_source === 'manual' && $latest->misting_system) {
                $desiredMode = 'manual';
                $desiredOn   = false;
            }
        }

        return response()->json([
            'desired_on'      => $desiredOn,
            'desired_mode'    => $desiredMode,
            'desired_profile' => $desiredProfile,
            'updated_at'      => $row && $row->updated_at ? (string) $row->updated_at : null,
            'mushroom_type'   => $key,
            'targets'         => $targets,
            // Fan fields
            'fan_desired_on'  => $fanRow ? (bool) $fanRow->desired_on : false,
            'fan_desired_mode'=> $fanRow && $fanRow->desired_mode ? (string) $fanRow->desired_mode : 'auto',
        ]);
    }

    /** POST /api/fan/control — set fan state from dashboard */
    public function controlFan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|boolean',
            'mode'   => 'nullable|string|in:auto,manual',
        ]);
        $mode      = $validated['mode'] ?? 'manual';
        $desiredOn = (bool) $validated['status'];
        // Auto mode always starts with fan off; ESP32 decides based on temp
        if ($mode === 'auto') $desiredOn = false;
        DB::table('fan_control')->updateOrInsert(
            ['id' => 1],
            ['desired_on' => $desiredOn, 'desired_mode' => $mode, 'updated_at' => now(), 'created_at' => now()]
        );
        return response()->json([
            'success'    => true,
            'message'    => 'Fan ' . ($desiredOn ? 'activated' : 'deactivated'),
            'desired_on' => $desiredOn,
            'desired_mode' => $mode,
        ]);
    }

    /** GET /api/fan/status — ESP32 polls this to know what to do */
    public function getFanStatus(): JsonResponse
    {
        $row = DB::table('fan_control')->where('id', 1)->first();
        return response()->json([
            'desired_on'   => $row ? (bool) $row->desired_on : false,
            'desired_mode' => $row && $row->desired_mode ? (string) $row->desired_mode : 'auto',
            'updated_at'   => $row && $row->updated_at ? (string) $row->updated_at : null,
        ]);
    }

    public function getHistory(Request $request): JsonResponse
    {
        $limit    = min(max((int) $request->get('limit', 15), 1), 100);
        $boxId    = $request->query('box');
        $interval = strtolower((string) $request->query('interval', 'raw'));

        $validIntervals = ['5m', '30m', '1h', '1d', '1w', '1mo'];

        $baseQuery = SensorData::query();
        if ($boxId) {
            if ($boxId === 'box_a') {
                $baseQuery->where(function ($q) { $q->where('box_id', 'box_a')->orWhereNull('box_id'); });
            } else {
                $baseQuery->where('box_id', $boxId);
            }
        }

        if (in_array($interval, $validIntervals, true) && DB::getDriverName() === 'mysql') {
            $bucketExpr = match ($interval) {
                '5m'  => 'FLOOR(UNIX_TIMESTAMP(recorded_at) / 300)',
                '30m' => 'FLOOR(UNIX_TIMESTAMP(recorded_at) / 1800)',
                '1h'  => 'FLOOR(UNIX_TIMESTAMP(recorded_at) / 3600)',
                '1d'  => 'DATE(DATE_ADD(recorded_at, INTERVAL 8 HOUR))',
                '1w'  => 'YEARWEEK(DATE_ADD(recorded_at, INTERVAL 8 HOUR), 1)',
                '1mo' => "DATE_FORMAT(DATE_ADD(recorded_at, INTERVAL 8 HOUR), '%Y-%m')",
            };

            $rows = $baseQuery
                ->selectRaw(
                    "{$bucketExpr} as bucket, " .
                    "ROUND(AVG(temperature), 2) as temperature, " .
                    "ROUND(AVG(humidity), 2) as humidity, " .
                    "ROUND(MIN(temperature), 2) as min_temperature, " .
                    "ROUND(MAX(temperature), 2) as max_temperature, " .
                    "ROUND(MIN(humidity), 2) as min_humidity, " .
                    "ROUND(MAX(humidity), 2) as max_humidity, " .
                    "COUNT(*) as sample_count, " .
                    "MIN(recorded_at) as earliest_at, " .
                    "MAX(recorded_at) as latest_at"
                )
                ->whereNotNull('recorded_at')
                ->groupBy('bucket')
                ->orderBy('latest_at', 'desc')
                ->limit($limit)
                ->get();

            $history = $rows->map(function ($row) use ($boxId, $interval) {
                $latestPh = \Illuminate\Support\Carbon::parse($row->latest_at)->timezone('Asia/Manila');
                $periodLabel = match ($interval) {
                    '5m' => (function () use ($latestPh) {
                        $start = $latestPh->copy()->minute((int) floor($latestPh->minute / 5) * 5)->second(0);
                        $end   = $start->copy()->addMinutes(5);
                        return $start->format('M d, g:i A') . ' – ' . $end->format('g:i A');
                    })(),
                    '30m' => (function () use ($latestPh) {
                        $start = $latestPh->copy()->minute($latestPh->minute >= 30 ? 30 : 0)->second(0);
                        $end   = $start->copy()->addMinutes(30);
                        return $start->format('M d, g:i A') . ' – ' . $end->format('g:i A');
                    })(),
                    '1h' => (function () use ($latestPh) {
                        $start = $latestPh->copy()->minute(0)->second(0);
                        $end   = $start->copy()->addHour();
                        return $start->format('M d, g:i A') . ' – ' . $end->format('g:i A');
                    })(),
                    '1d' => $latestPh->format('F d, Y'),
                    '1w' => (function () use ($latestPh) {
                        $start = $latestPh->copy()->startOfWeek(\Illuminate\Support\Carbon::MONDAY);
                        $end   = $start->copy()->endOfWeek(\Illuminate\Support\Carbon::SUNDAY);
                        return $start->format('M d') . ' – ' . $end->format('M d, Y');
                    })(),
                    '1mo' => (function () use ($latestPh) {
                        $start = $latestPh->copy()->startOfMonth();
                        $next  = $start->copy()->addMonth();
                        return $start->format('F') . ' – ' . $next->format('F Y');
                    })(),
                };

                return [
                    'box_id'          => $boxId ?? 'box_a',
                    'temperature'     => $row->temperature !== null ? (float) $row->temperature : null,
                    'humidity'        => $row->humidity !== null ? (float) $row->humidity : null,
                    'min_temperature' => $row->min_temperature !== null ? (float) $row->min_temperature : null,
                    'max_temperature' => $row->max_temperature !== null ? (float) $row->max_temperature : null,
                    'min_humidity'    => $row->min_humidity !== null ? (float) $row->min_humidity : null,
                    'max_humidity'    => $row->max_humidity !== null ? (float) $row->max_humidity : null,
                    'sample_count'    => (int) $row->sample_count,
                    'interval'        => $interval,
                    'period_label'    => $periodLabel,
                    'recorded_at'     => \Illuminate\Support\Carbon::parse($row->latest_at)->toIso8601String(),
                ];
            });

            return response()->json($history)
                ->header('Cache-Control', 'private, max-age=4');
        }

        $query = $baseQuery->select(
                'box_id','temperature','humidity','wifi_rssi',
                'misting_system','misting_source','misting_reason',
                'misting_total_ms','misting_last_burst_ms','recorded_at'
            )
            ->orderBy('recorded_at', 'desc')
            ->limit($limit);

        $history = $query->get()->map(function ($data) {
            return [
                'box_id'                => $data->box_id ?? 'box_a',
                'temperature'           => $data->temperature,
                'humidity'              => $data->humidity,
                'wifi_rssi'             => $data->wifi_rssi,
                'misting_system'        => $data->misting_system,
                'misting_source'        => $data->misting_source,
                'misting_reason'        => $data->misting_reason,
                'misting_total_ms'      => $data->misting_total_ms,
                'misting_last_burst_ms' => $data->misting_last_burst_ms,
                'sample_count'          => 1,
                'interval'              => 'raw',
                'recorded_at'           => $data->recorded_at->toIso8601String(),
            ];
        });
        return response()->json($history)
            ->header('Cache-Control', 'private, max-age=4');
    }

    private function formatRow(SensorData $row): array
    {
        return [
            'box_id'               => $row->box_id ?? 'box_a',
            'temperature'          => $row->temperature !== null ? (float) $row->temperature : null,
            'humidity'             => $row->humidity !== null ? (float) $row->humidity : null,
            'wifi_rssi'            => $row->wifi_rssi,
            'misting_system'       => (bool) $row->misting_system,
            'misting_source'       => $row->misting_source,
            'misting_reason'       => $row->misting_reason,
            'misting_total_ms'     => $row->misting_total_ms,
            'misting_last_burst_ms'=> $row->misting_last_burst_ms,
            'fan_system'           => (bool) ($row->fan_system ?? false),
            'recorded_at'          => $row->recorded_at ? $row->recorded_at->toIso8601String() : null,
        ];
    }
}
