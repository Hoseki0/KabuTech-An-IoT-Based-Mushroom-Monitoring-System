<?php

namespace App\Services;

use App\Models\SensorData;
use App\Models\SystemNotification;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Detects IoT hardware problems and notifies users (bell menu + Web Push).
 *
 * Checks:
 *  - ESP32 sensor node offline (no readings received)
 *  - SHT31 sensor fault (frozen / saturated / impossible values)
 *  - Misting relay not following the manual command
 *  - Fan relay not following the manual command
 *  - Weak Wi-Fi signal on the ESP32
 *  - ESP32-CAM offline (only when $includeCamera = true)
 *
 * Each problem alerts once, then is throttled. A "back to normal" message is
 * sent once when a problem clears.
 */
final class DeviceHealthService
{
    private const OFFLINE_AFTER_SECONDS   = 120;   // no data for 2 min = offline
    private const REMIND_EVERY_SECONDS    = 1800;  // re-alert every 30 min while still broken
    private const RELAY_GRACE_SECONDS     = 20;    // time the ESP32 gets to apply a command
    private const WEAK_RSSI_DBM           = -85;

    /** @return array<string, bool> problem key => currently failing */
    public function check(bool $includeCamera = false): array
    {
        $status = [];

        $latest = SensorData::latest('recorded_at')->first();

        $status['esp32_offline'] = $this->checkOffline($latest);

        // Remaining checks need a recent reading
        if ($latest && !$status['esp32_offline']) {
            $status['sensor_fault']  = $this->checkSensorFault();
            $status['misting_relay'] = $this->checkRelay('misting_control', 'misting_system', 'Misting pump relay', $latest);
            $status['fan_relay']     = $this->checkRelay('fan_control', 'fan_system', 'Exhaust fan relay', $latest);
            $status['weak_wifi']     = $this->checkWifi();
        }

        if ($includeCamera) {
            $status['camera_offline'] = $this->checkCamera();
        }

        return $status;
    }

    private function checkOffline(?SensorData $latest): bool
    {
        if (!$latest || !$latest->recorded_at) {
            return $this->problem('esp32_offline', 'ESP32 sensor node offline',
                'No sensor readings have been received yet. Check that the ESP32 is powered on and connected to Wi-Fi.');
        }

        $age = now()->diffInSeconds($latest->recorded_at, true);
        if ($age > self::OFFLINE_AFTER_SECONDS) {
            $mins = (int) round($age / 60);
            $last = Carbon::parse($latest->recorded_at)->timezone('Asia/Manila')->format('M d, g:i A');
            return $this->problem('esp32_offline', 'ESP32 sensor node offline',
                "No readings for {$mins} min (last seen {$last}). Check power, Wi-Fi, and that the server URL is reachable.");
        }

        return $this->resolved('esp32_offline', 'ESP32 sensor node back online',
            'Sensor readings are being received again.');
    }

    private function checkSensorFault(): bool
    {
        $rows = SensorData::latest('recorded_at')->take(12)->get(['temperature', 'humidity']);
        if ($rows->count() < 12) {
            return false;
        }

        $temps = $rows->pluck('temperature')->filter(fn ($v) => $v !== null)->map(fn ($v) => round((float) $v, 2));
        $hums  = $rows->pluck('humidity')->filter(fn ($v) => $v !== null)->map(fn ($v) => round((float) $v, 2));

        $reason = null;
        if ($temps->isEmpty() || $hums->isEmpty()) {
            $reason = 'The SHT31 is sending empty readings.';
        } elseif ($temps->contains(fn ($t) => $t < -10 || $t > 60) || $hums->contains(fn ($h) => $h < 0 || $h > 100)) {
            $reason = 'The SHT31 reported impossible values (check wiring on SDA GPIO 21 / SCL GPIO 22).';
        } elseif ($hums->every(fn ($h) => $h >= 99.9)) {
            $reason = 'Humidity has been stuck at 100% for the last minute — the sensor may be wet or covered by misting spray.';
        } elseif ($temps->unique()->count() === 1 && $hums->unique()->count() === 1) {
            $reason = 'Temperature and humidity have not changed at all for the last 12 readings — the sensor may be frozen.';
        }

        if ($reason) {
            return $this->problem('sensor_fault', 'SHT31 sensor problem detected', $reason);
        }

        return $this->resolved('sensor_fault', 'SHT31 sensor readings normal',
            'Temperature and humidity readings look healthy again.');
    }

    private function checkRelay(string $table, string $column, string $label, SensorData $latest): bool
    {
        $key = "{$column}_relay";
        $control = DB::table($table)->where('id', 1)->first();

        // Only manual mode has a fixed expected state
        if (!$control || ($control->desired_mode ?? 'auto') !== 'manual') {
            return $this->resolved($key, "{$label} responding", "{$label} is following commands again.");
        }

        $commandAt = $control->updated_at ? Carbon::parse($control->updated_at) : null;
        if ($commandAt && now()->diffInSeconds($commandAt, true) < self::RELAY_GRACE_SECONDS) {
            return false; // still within grace period
        }

        $expected = (bool) $control->desired_on;
        $recent = SensorData::latest('recorded_at')
            ->when($commandAt, fn ($q) => $q->where('recorded_at', '>=', $commandAt))
            ->take(3)
            ->pluck($column);

        if ($recent->count() < 3) {
            return false;
        }

        if ($recent->every(fn ($v) => (bool) $v !== $expected)) {
            $want = $expected ? 'ON' : 'OFF';
            return $this->problem($key, "{$label} not responding",
                "Dashboard set it to {$want} (Manual), but the ESP32 keeps reporting the opposite. Check the relay wiring (COM/NO) and power.");
        }

        return $this->resolved($key, "{$label} responding", "{$label} is following commands again.");
    }

    private function checkWifi(): bool
    {
        $avg = SensorData::latest('recorded_at')->take(12)->whereNotNull('wifi_rssi')->pluck('wifi_rssi')->avg();
        if ($avg !== null && $avg < self::WEAK_RSSI_DBM) {
            return $this->problem('weak_wifi', 'Weak Wi-Fi signal on ESP32',
                sprintf('Average signal is %d dBm. Move the router closer to the incubator to avoid dropped readings.', (int) $avg));
        }

        return $this->resolved('weak_wifi', 'ESP32 Wi-Fi signal recovered', 'Wi-Fi signal strength is back to normal.');
    }

    private function checkCamera(): bool
    {
        $raw = trim((string) config('iot.camera_stream_url', ''));
        if ($raw === '') {
            return false; // camera not configured
        }
        if (!str_starts_with($raw, 'http')) {
            $raw = 'http://' . $raw;
        }
        $parts = parse_url($raw);
        $host = $parts['host'] ?? null;
        if (!$host) {
            return false;
        }

        $online = false;
        try {
            $res = (new Client(['connect_timeout' => 2, 'timeout' => 3, 'http_errors' => false]))
                ->get(($parts['scheme'] ?? 'http') . '://' . $host . '/status');
            $online = $res->getStatusCode() === 200;
        } catch (\Throwable $e) {
            $online = false;
        }

        if (!$online) {
            return $this->problem('camera_offline', 'ESP32-CAM offline',
                "The camera at {$host} is not responding. Check its 5V power and Wi-Fi, and update ESP32_CAM_STREAM_URL in .env if its IP changed.");
        }

        return $this->resolved('camera_offline', 'ESP32-CAM back online', 'The live camera is reachable again.');
    }

    /** Record a problem; notify on first detection and then every REMIND_EVERY_SECONDS. */
    private function problem(string $key, string $title, string $body): bool
    {
        $stateKey  = "device_health:state:{$key}";
        $remindKey = "device_health:remind:{$key}";

        Cache::forever($stateKey, 'failing');

        if (Cache::add($remindKey, true, self::REMIND_EVERY_SECONDS)) {
            $this->notify("device_{$key}", $title, $body);
        }

        return true;
    }

    /** Clear a problem; send a single recovery notice if it was previously failing. */
    private function resolved(string $key, string $title, string $body): bool
    {
        $stateKey = "device_health:state:{$key}";

        if (Cache::get($stateKey) === 'failing') {
            Cache::forget($stateKey);
            Cache::forget("device_health:remind:{$key}");
            $this->notify("device_{$key}_ok", $title, $body);
        }

        return false;
    }

    private function notify(string $type, string $title, string $body): void
    {
        SystemNotification::query()->create([
            'type'  => $type,
            'title' => $title,
            'body'  => $body,
        ]);

        try {
            WebPushService::sendToAll($title, $body, $type);
        } catch (\Throwable $e) {
            // never let push delivery failures break the health check
        }
    }
}
