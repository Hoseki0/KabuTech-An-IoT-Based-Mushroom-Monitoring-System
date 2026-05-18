<?php

namespace App\Services;

use Carbon\Carbon;

final class FruitingStartPredictionService
{
    /**
     * Predict when fruiting (pinning) may start if conditions are kept on target.
     *
     * Note: This is a simple, day-range estimate from the selected species profile.
     * It does NOT detect pins visually.
     *
     * @return array{
     *   basis: string,
     *   earliest_pins_at: string,
     *   latest_pins_at: string,
     *   days_until_earliest: int,
     *   days_until_latest: int,
     *   note: string
     * }|null
     */
    public static function predict(string $mushroomKey): ?array
    {
        $p = MushroomSpeciesCatalog::profile($mushroomKey) ?? MushroomSpeciesCatalog::profile(MushroomSpeciesCatalog::defaultKey());
        if ($p === null) {
            return null;
        }

        $now = Carbon::now()->startOfDay();
        $earliest = $now->copy()->addDays((int) ($p['pin_days_min'] ?? 0))->startOfDay();
        $latest = $now->copy()->addDays((int) ($p['pin_days_max'] ?? 0))->startOfDay();

        return [
            'basis' => 'today',
            'earliest_pins_at' => $earliest->toIso8601String(),
            'latest_pins_at' => $latest->toIso8601String(),
            'days_until_earliest' => max(0, (int) (($earliest->timestamp - Carbon::now()->timestamp) / 86400)),
            'days_until_latest' => max(0, (int) (($latest->timestamp - Carbon::now()->timestamp) / 86400)),
            'note' => 'Pins/fruiting start estimate for '.$p['label'].' if conditions stay within target.',
        ];
    }
}

