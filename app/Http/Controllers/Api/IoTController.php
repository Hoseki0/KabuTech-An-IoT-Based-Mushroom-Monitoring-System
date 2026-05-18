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
    /**
     * Get the latest sensor data
     */
    public function getLatest(): JsonResponse
    {
        $latest = SensorData::latest('recorded_at')->first();

        if (! $latest) {
            return response()->json([
                'temperature' => null,
                'humidity' => null,
                'misting_system' => false,
                'recorded_at' => null,
            ]);
        }

        return response()->json([
            'temperature' => $latest->temperature !== null ? (float) $latest->temperature : null,
            'humidity' => $latest->humidity !== null ? (float) $latest->humidity : null,
            'wifi_rssi' => $latest->wifi_rssi,
            'misting_system' => (bool) $latest->misting_system,
            'misting_source' => $latest->misting_source,
            'misting_reason' => $latest->misting_reason,
            'misting_total_ms' => $latest->misting_total_ms,
            'misting_last_burst_ms' => $latest->misting_last_burst_ms,
            'recorded_at' => $latest->recorded_at ? $latest->recorded_at->toIso8601String() : null,
        ]);
    }

    /**
     * Receive sensor data from IoT device
     */
    public function receiveData(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'temperature' => 'nullable|numeric|between:-50,100',
                'humidity' => 'nullable|numeric|between:0,100',
                'misting_system' => 'nullable|boolean',
                'wifi_rssi' => 'nullable|integer|between:-120,0',
                'misting_source' => 'nullable|string|in:auto,manual',
                'misting_reason' => 'nullable|string|max:32',
                'misting_total_ms' => 'nullable|integer|min:0',
                'misting_last_burst_ms' => 'nullable|integer|min:0|max:600000',
            ]);

            $sensorData = SensorData::create([
                'temperature' => $validated['temperature'] ?? null,
                'humidity' => $validated['humidity'] ?? null,
                'wifi_rssi' => $validated['wifi_rssi'] ?? null,
                'misting_system' => $validated['misting_system'] ?? false,
                'misting_source' => $validated['misting_source'] ?? null,
                'misting_reason' => $validated['misting_reason'] ?? null,
                'misting_total_ms' => $validated['misting_total_ms'] ?? null,
                'misting_last_burst_ms' => $validated['misting_last_burst_ms'] ?? null,
                'recorded_at' => now(),
            ]);

            try {
                app(GrowAlertService::class)->evaluateAfterReading($sensorData);
            } catch (\Throwable $e) {
                \Log::warning('Grow alert evaluation failed', ['message' => $e->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sensor data received',
                'data' => $sensorData,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Sensor data validation failed', ['errors' => $e->errors()]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Sensor data save failed', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error saving sensor data',
            ], 500);
        }
    }

    /**
     * Control misting system
     */
    public function controlMisting(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|boolean',
            'mode' => 'nullable|string|in:auto,manual',
            'profile' => 'nullable|string|in:incubation,fruiting',
        ]);

        $mode = $validated['mode'] ?? 'manual';
        $profile = $validated['profile'] ?? 'fruiting';
        $desiredOn = (bool) $validated['status'];

        if ($mode === 'auto') {
            $desiredOn = false;
        }

        DB::table('misting_control')->updateOrInsert(
            ['id' => 1],
            [
                'desired_on' => $desiredOn,
                'desired_mode' => $mode,
                'desired_profile' => $profile,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Misting system '.($validated['status'] ? 'activated' : 'deactivated'),
            'desired_on' => $desiredOn,
            'desired_mode' => $mode,
            'desired_profile' => $profile,
        ]);
    }

    /**
     * Get desired misting state (manual command) + species targets for AUTO misting on ESP32.
     */
    public function getMistingStatus(): JsonResponse
    {
        $row = DB::table('misting_control')->where('id', 1)->first();

        $grow = GrowSetting::singleton();
        $key = MushroomSpeciesCatalog::isValidKey($grow->mushroom_type)
            ? $grow->mushroom_type
            : MushroomSpeciesCatalog::defaultKey();
        $p = MushroomSpeciesCatalog::profile($key);

        $desiredProfile = $row && $row->desired_profile ? (string) $row->desired_profile : 'fruiting';
        $targets = null;
        if ($p) {
            if ($desiredProfile === 'incubation') {
                $targets = [
                    'temp_min' => isset($p['incubation_temp_min']) ? (float) $p['incubation_temp_min'] : null,
                    'temp_max' => isset($p['incubation_temp_max']) ? (float) $p['incubation_temp_max'] : null,
                    'hum_min' => isset($p['incubation_hum_min']) ? (float) $p['incubation_hum_min'] : null,
                    'hum_max' => isset($p['incubation_hum_max']) ? (float) $p['incubation_hum_max'] : null,
                ];
            } else {
                $targets = [
                    'temp_min' => (float) $p['temp_min'],
                    'temp_max' => (float) $p['temp_max'],
                    'hum_min' => (float) $p['hum_min'],
                    'hum_max' => (float) $p['hum_max'],
                ];
            }
        }

        return response()->json([
            'desired_on' => $row ? (bool) $row->desired_on : false,
            'desired_mode' => $row && $row->desired_mode ? (string) $row->desired_mode : 'auto',
            'desired_profile' => $desiredProfile,
            'updated_at' => $row && $row->updated_at ? (string) $row->updated_at : null,
            'mushroom_type' => $key,
            'targets' => $targets,
        ]);
    }

    /**
     * Get sensor data history
     */
    public function getHistory(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 15), 100);

        $history = SensorData::orderBy('recorded_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($data) {
                return [
                    'temperature' => $data->temperature,
                    'humidity' => $data->humidity,
                    'wifi_rssi' => $data->wifi_rssi,
                    'misting_system' => $data->misting_system,
                    'misting_source' => $data->misting_source,
                    'misting_reason' => $data->misting_reason,
                    'misting_total_ms' => $data->misting_total_ms,
                    'misting_last_burst_ms' => $data->misting_last_burst_ms,
                    'recorded_at' => $data->recorded_at->toIso8601String(),
                ];
            });

        return response()->json($history);
    }
}
