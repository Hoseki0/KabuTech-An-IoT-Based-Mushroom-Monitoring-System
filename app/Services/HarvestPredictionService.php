<?php

namespace App\Services;

use Carbon\Carbon;

final class HarvestPredictionService
{
    /**
     * @return array{
     *   fruiting_started_at: string,
     *   earliest_harvest_at: string,
     *   latest_harvest_at: string,
     *   days_until_earliest: int|null,
     *   days_until_latest: int|null,
     *   note: string
     * }|null
     */
    public static function predict(?Carbon $fruitingStartedAt, string $mushroomKey): ?array
    {
        if ($fruitingStartedAt === null) {
            return null;
        }

        $p = MushroomSpeciesCatalog::profile($mushroomKey) ?? MushroomSpeciesCatalog::profile(MushroomSpeciesCatalog::defaultKey());
        if ($p === null) {
            return null;
        }

        $start = $fruitingStartedAt->copy()->startOfDay();
        $earliest = $start->copy()->addDays($p['harvest_days_min'])->startOfDay();
        $latest = $start->copy()->addDays($p['harvest_days_max'])->startOfDay();
        $now = Carbon::now();

        $daysEarliest = (int) floor(($earliest->timestamp - $now->timestamp) / 86400);
        $daysLatest = (int) floor(($latest->timestamp - $now->timestamp) / 86400);

        return [
            'fruiting_started_at' => $fruitingStartedAt->toIso8601String(),
            'earliest_harvest_at' => $earliest->toIso8601String(),
            'latest_harvest_at' => $latest->toIso8601String(),
            'days_until_earliest' => $daysEarliest,
            'days_until_latest' => $daysLatest,
            'note' => 'Estimate from typical fruiting duration for '.$p['label'].'. Adjust with real pin-set and strain.',
        ];
    }
}
