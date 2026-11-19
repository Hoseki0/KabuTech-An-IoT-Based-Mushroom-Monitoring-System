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
        $minDays = (float) ($p['incubation_days_min'] ?? 14);
        $maxDays = (float) ($p['incubation_days_max'] ?? 21);
        $nominalDays = ($minDays + $maxDays) / 2.0;

        $earliest = $start->copy()->addDays((int) $minDays)->startOfDay();
        $latest = $start->copy()->addDays((int) $maxDays)->startOfDay();
        $predicted = $start->copy()->addDays((int) round($nominalDays))->startOfDay();
        $now = Carbon::now();

        $daysElapsed = max(0.0, round($start->diffInSeconds($now) / 86400, 1));
        $growthProgressPercent = min(100, max(0, (int) round(($daysElapsed / max(1.0, $nominalDays)) * 100)));
        $daysRemaining = max(0.0, round($nominalDays - $daysElapsed, 1));

        return [
            'phase' => 'incubation',
            'incubation_started_at' => $incubationStartedAt->toIso8601String(),
            'earliest_fruiting_switch_at' => $earliest->toIso8601String(),
            'latest_fruiting_switch_at' => $latest->toIso8601String(),
            'predicted_fruiting_switch_at' => $predicted->toIso8601String(),
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            'nominal_total_days' => $nominalDays,
            'days_until_earliest' => (int) floor(($earliest->timestamp - $now->timestamp) / 86400),
            'days_until_latest' => (int) floor(($latest->timestamp - $now->timestamp) / 86400),
            'growth_progress_percent' => $growthProgressPercent,
            'confidence_score' => 90,
            'note' => 'Incubation (spawn run) estimate for '.$p['label'].'. Confirm full colonization before fruiting.',
        ];
    }
}

