<?php

namespace App\Services;

use App\Models\GrowSetting;
use App\Models\SensorData;
use App\Models\SystemNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

final class GrowAlertService
{
    private const THROTTLE_SECONDS = 3600;

    public function evaluateAfterReading(SensorData $reading): void
    {
        $settings = GrowSetting::singleton();
        $key = $settings->mushroom_type;
        if (! MushroomSpeciesCatalog::isValidKey($key)) {
            $key = MushroomSpeciesCatalog::defaultKey();
        }

        $p = MushroomSpeciesCatalog::profile($key);
        if ($p === null) {
            return;
        }

        $t = $reading->temperature !== null ? (float) $reading->temperature : null;
        $h = $reading->humidity !== null ? (float) $reading->humidity : null;

        if ($t !== null) {
            if ($t < $p['temp_min']) {
                $this->maybeNotify(
                    'env_temp_low',
                    'Temperature below target',
                    sprintf('%.1f °C is below the %s minimum (%.1f °C).', $t, $p['label'], $p['temp_min'])
                );
            } elseif ($t > $p['temp_max']) {
                $this->maybeNotify(
                    'env_temp_high',
                    'Temperature above target',
                    sprintf('%.1f °C is above the %s maximum (%.1f °C).', $t, $p['label'], $p['temp_max'])
                );
            }
        }

        if ($h !== null) {
            if ($h < $p['hum_min']) {
                $this->maybeNotify(
                    'env_hum_low',
                    'Humidity below target',
                    sprintf('%.1f %% RH is below the %s minimum (%.1f %%).', $h, $p['label'], $p['hum_min'])
                );
            } elseif ($h > $p['hum_max']) {
                $this->maybeNotify(
                    'env_hum_high',
                    'Humidity above target',
                    sprintf('%.1f %% RH is above the %s maximum (%.1f %%).', $h, $p['label'], $p['hum_max'])
                );
            }
        }

        $started = $settings->fruiting_started_at;
        if ($started instanceof Carbon) {
            $pred = HarvestPredictionService::predict($started, $key);
            if ($pred !== null && isset($pred['days_until_earliest'])) {
                $d = $pred['days_until_earliest'];
                if ($d >= 0 && $d <= 2) {
                    $this->maybeNotify(
                        'harvest_soon',
                        'Harvest window approaching',
                        sprintf('%s: earliest typical harvest in ~%d day(s) (by %s).', $p['label'], max(0, $d), Carbon::parse($pred['earliest_harvest_at'])->toDateString()),
                        'harvest_soon'
                    );
                }
            }
        }
    }

    private function maybeNotify(string $type, string $title, string $body, ?string $cacheKey = null): void
    {
        $cacheKey ??= $type;
        $ck = 'grow_alert:'.$cacheKey;
        if (Cache::has($ck)) {
            return;
        }

        SystemNotification::query()->create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
        ]);

        Cache::put($ck, true, self::THROTTLE_SECONDS);
    }
}
