<?php

namespace App\Services;

use App\Models\SensorData;
use Carbon\Carbon;

final class IncubationPredictionService
{
    /**
     * Predict when incubation may finish using species spawn-run targets
     * adjusted by real SensorData temperature & humidity telemetry.
     *
     * @return array{
     *   phase: string,
     *   incubation_started_at: string,
     *   earliest_fruiting_switch_at: string,
     *   latest_fruiting_switch_at: string,
     *   predicted_fruiting_switch_at: string,
     *   days_elapsed: float,
     *   days_remaining: float,
     *   nominal_total_days: float,
     *   days_until_earliest: int,
     *   days_until_latest: int,
     *   growth_progress_percent: int,
     *   confidence_score: int,
     *   readings_analyzed: int,
     *   avg_temperature: float|null,
     *   avg_humidity: float|null,
     *   climate_efficiency_percent: float|null,
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

        $tMin = (float) ($p['incubation_temp_min'] ?? 24.0);
        $tMax = (float) ($p['incubation_temp_max'] ?? 27.0);
        $hMin = (float) ($p['incubation_hum_min'] ?? 60.0);
        $hMax = (float) ($p['incubation_hum_max'] ?? 75.0);

        $now = Carbon::now();
        $daysElapsed = max(0.0, round($start->diffInSeconds($now) / 86400, 1));

        // Query real sensor telemetry recorded since incubation started
        $stats = SensorData::where('recorded_at', '>=', $incubationStartedAt)
            ->selectRaw('COUNT(*) as cnt, AVG(temperature) as avg_temp, AVG(humidity) as avg_hum')
            ->first();

        $readingsCount = $stats ? (int) $stats->cnt : 0;
        $avgTemp = ($readingsCount > 0 && $stats->avg_temp !== null) ? round((float) $stats->avg_temp, 1) : null;
        $avgHum  = ($readingsCount > 0 && $stats->avg_hum !== null) ? round((float) $stats->avg_hum, 1) : null;

        $adjustedTotalDays = $nominalDays;
        $climateEfficiency = null;
        $confidenceScore = 90;
        $note = 'Incubation (spawn run) estimate for ' . $p['label'] . '. Confirm full white mycelium colonization before fruiting.';

        if ($avgTemp !== null && $avgHum !== null) {
            $optTemp   = ($tMin + $tMax) / 2.0;
            $tempRange = max(1.0, ($tMax - $tMin) / 2.0);
            if ($avgTemp >= $tMin && $avgTemp <= $tMax) {
                $tempEff = 100.0 - (abs($avgTemp - $optTemp) / $tempRange) * 10.0;
            } else {
                $dist = ($avgTemp < $tMin) ? ($tMin - $avgTemp) : ($avgTemp - $tMax);
                $tempEff = max(55.0, 90.0 - ($dist * 6.0));
            }

            $optHum   = ($hMin + $hMax) / 2.0;
            $humRange = max(1.0, ($hMax - $hMin) / 2.0);
            if ($avgHum >= $hMin && $avgHum <= $hMax) {
                $humEff = 100.0 - (abs($avgHum - $optHum) / $humRange) * 8.0;
            } else {
                $dist = ($avgHum < $hMin) ? ($hMin - $avgHum) : ($avgHum - $hMax);
                $humEff = max(65.0, 92.0 - ($dist * 1.5));
            }

            $climateEfficiency = round(($tempEff * 0.65) + ($humEff * 0.35), 1);
            $growthRate = max(0.65, min(1.05, $climateEfficiency / 100.0));
            $adjustedTotalDays = round(max($minDays, min($maxDays + 4.0, $nominalDays / $growthRate)), 1);
            $confidenceScore = (int) round(min(96, max(65, ($climateEfficiency * 0.6) + ($readingsCount > 20 ? 36 : 28))));

            if ($avgTemp > $tMax) {
                $note = sprintf('Avg temp %.1f°C is above %s incubation max (%.1f°C), slowing colonization to ~%.1f days. Confirm full white mycelium visually.', $avgTemp, $p['label'], $tMax, $adjustedTotalDays);
            } elseif ($avgTemp < $tMin) {
                $note = sprintf('Avg temp %.1f°C is below %s incubation min (%.1f°C), slowing colonization to ~%.1f days. Confirm full white mycelium visually.', $avgTemp, $p['label'], $tMin, $adjustedTotalDays);
            } else {
                $note = sprintf('Incubation climate is optimal (avg %.1f°C, %.0f%% RH). Confirm full white mycelium before fruiting.', $avgTemp, $avgHum);
            }
        }

        $earliest = $start->copy()->addDays((int) $minDays)->startOfDay();
        $latest = $start->copy()->addDays((int) round(max($maxDays, $adjustedTotalDays)))->startOfDay();
        $predicted = $start->copy()->addMinutes((int) round($adjustedTotalDays * 1440));

        $growthProgressPercent = min(100, max(0, (int) round(($daysElapsed / max(1.0, $adjustedTotalDays)) * 100)));
        $daysRemaining = max(0.0, round($adjustedTotalDays - $daysElapsed, 1));

        return [
            'phase' => 'incubation',
            'incubation_started_at' => $incubationStartedAt->toIso8601String(),
            'earliest_fruiting_switch_at' => $earliest->toIso8601String(),
            'latest_fruiting_switch_at' => $latest->toIso8601String(),
            'predicted_fruiting_switch_at' => $predicted->toIso8601String(),
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            'nominal_total_days' => $adjustedTotalDays,
            'days_until_earliest' => (int) floor(($earliest->timestamp - $now->timestamp) / 86400),
            'days_until_latest' => (int) floor(($latest->timestamp - $now->timestamp) / 86400),
            'growth_progress_percent' => $growthProgressPercent,
            'confidence_score' => $confidenceScore,
            'readings_analyzed' => $readingsCount,
            'avg_temperature' => $avgTemp,
            'avg_humidity' => $avgHum,
            'climate_efficiency_percent' => $climateEfficiency,
            'note' => $note,
        ];
    }
}
