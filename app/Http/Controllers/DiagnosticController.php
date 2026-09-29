<?php

namespace App\Http\Controllers;

use App\Models\SensorData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiagnosticController extends Controller
{
    /** Get hardware diagnostic metrics */
    public function getHardwareMetrics(Request $request): JsonResponse
    {
        $boxId = $request->query('box_id', 'box_a');
        $now = now();
        $since24h = $now->copy()->subHours(24);
        $since7d  = $now->copy()->subDays(7);

        $query24h = SensorData::where('recorded_at', '>=', $since24h);
        $query7d  = SensorData::where('recorded_at', '>=', $since7d);

        if ($boxId === 'box_a') {
            $query24h->where(function ($q) { $q->where('box_id', 'box_a')->orWhereNull('box_id'); });
            $query7d->where(function ($q) { $q->where('box_id', 'box_a')->orWhereNull('box_id'); });
        } else {
            $query24h->where('box_id', $boxId);
            $query7d->where('box_id', $boxId);
        }

        $readings24hCount = (clone $query24h)->count();
        $mistingActivations24h = (clone $query24h)->where('misting_system', true)->count();
        $totalMistingMs24h = (clone $query24h)->sum('misting_last_burst_ms') ?: ((clone $query24h)->where('misting_system', true)->count() * 10000);

        $avgRssi = (clone $query24h)->whereNotNull('wifi_rssi')->avg('wifi_rssi');
        $rssiVal = $avgRssi !== null ? round((float) $avgRssi, 1) : -65.0;

        $rssiStatus = 'Unknown';
        if ($rssiVal >= -60) {
            $rssiStatus = 'Excellent Signal';
        } elseif ($rssiVal >= -75) {
            $rssiStatus = 'Good Signal';
        } elseif ($rssiVal >= -85) {
            $rssiStatus = 'Fair Signal';
        } else {
            $rssiStatus = 'Weak Signal';
        }

        $latestReading = SensorData::latest('recorded_at')->first();
        $deviceConnected = $latestReading && $now->diffInSeconds($latestReading->recorded_at) < 180;

        $pumpRuntimeSeconds24h = round($totalMistingMs24h / 1000, 1);
        $pumpRuntimeMinutes24h = round($pumpRuntimeSeconds24h / 60, 2);

        return response()->json([
            'success' => true,
            'data' => [
                'box_id'                    => $boxId,
                'device_connected'          => $deviceConnected,
                'last_telemetry_at'         => $latestReading ? $latestReading->recorded_at->toIso8601String() : null,
                'telemetry_packets_24h'     => $readings24hCount,
                'misting_activations_24h'   => $mistingActivations24h,
                'pump_runtime_seconds_24h'  => $pumpRuntimeSeconds24h,
                'pump_runtime_minutes_24h'  => $pumpRuntimeMinutes24h,
                'avg_wifi_rssi_dbm'         => $rssiVal,
                'wifi_signal_quality'       => $rssiStatus,
                'estimated_duty_cycle_pct'  => round(($pumpRuntimeSeconds24h / 86400) * 100, 3),
            ],
        ]);
    }
}
