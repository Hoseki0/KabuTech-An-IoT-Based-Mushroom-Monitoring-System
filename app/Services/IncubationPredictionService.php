<?php

namespace App\Services;

use Carbon\Carbon;

final class IncubationPredictionService
{
    /**
     * Predict when incubation may finish and you should switch to fruiting conditions.
     *
     * @return array{
     *   incubation_started_at: string,
     *   earliest_fruiting_switch_at: string,
     *   latest_fruiting_switch_at: string,
     *   days_until_earliest: int,
     *   days_until_latest: int,
     *   note: string
     * }|null
     */
    public static function predict(?Carbon $incubationStartedAt, string $mushroomKey): ?array
    {
        if ($incubationStartedAt === null) {
            return null;
        }

        $p = MushroomSpeciesCatalog::profile($mushroomKey) ?? MushroomSpeciesCatalog::profile(MushroomSpeciesCatalog::defaultKey());
        if ($p === null) {
            return null;
        }

        $start = $incubationStartedAt->copy()->startOfDay();
        $earliest = $start->copy()->addDays((int) ($p['incubation_days_min'] ?? 0))->startOfDay();
        $latest = $start->copy()->addDays((int) ($p['incubation_days_max'] ?? 0))->startOfDay();
        $now = Carbon::now();

        return [
            'incubation_started_at' => $incubationStartedAt->toIso8601String(),
            'earliest_fruiting_switch_at' => $earliest->toIso8601String(),
            'latest_fruiting_switch_at' => $latest->toIso8601String(),
            'days_until_earliest' => (int) floor(($earliest->timestamp - $now->timestamp) / 86400),
            'days_until_latest' => (int) floor(($latest->timestamp - $now->timestamp) / 86400),
            'note' => 'Incubation (spawn run) estimate for '.$p['label'].'. Confirm full colonization before fruiting.',
        ];
    }
}

