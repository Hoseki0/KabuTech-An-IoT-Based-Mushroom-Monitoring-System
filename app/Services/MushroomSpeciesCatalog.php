<?php

namespace App\Services;

/**
 * Fruiting-stage targets (approximate, educational).
 * Harvest window = typical days after pinning / fruiting start (wide ranges).
 */
final class MushroomSpeciesCatalog
{
    public const KEYS = [
        'oyster_white',
        'oyster_brown',
    ];

    /**
     * Legacy keys (from older versions) mapped to a current key.
     */
    public const LEGACY_ALIASES = [
        'oyster_mushroom' => 'oyster_white',
    ];

    /**
     * @return array<string, array{
     *   label: string,
     *   incubation_temp_min: float, incubation_temp_max: float,
     *   incubation_hum_min: float, incubation_hum_max: float,
     *   temp_min: float, temp_max: float,
     *   hum_min: float, hum_max: float,
     *   incubation_days_min: int, incubation_days_max: int,
     *   pin_days_min: int, pin_days_max: int,
     *   harvest_days_min: int, harvest_days_max: int
     * }>
     */
    public static function all(): array
    {
        return [
            'oyster_white' => [
                'label' => 'White Oyster Mushroom',
                // Incubation (spawn run) targets
                'incubation_temp_min' => 24.0,
                'incubation_temp_max' => 27.0,
                'incubation_hum_min' => 60.0,
                'incubation_hum_max' => 75.0,
                'temp_min' => 15.0,
                'temp_max' => 24.0,
                'hum_min' => 80.0,
                'hum_max' => 95.0,
                // Spawn run duration before inducing fruiting (typical).
                'incubation_days_min' => 10,
                'incubation_days_max' => 21,
                // Typical time to pins after fruiting conditions are introduced.
                'pin_days_min' => 3,
                'pin_days_max' => 10,
                'harvest_days_min' => 5,
                'harvest_days_max' => 14,
            ],
            'oyster_brown' => [
                'label' => 'Brown Oyster Mushroom',
                // Brown/grey oyster strains tolerate warmer conditions.
                'incubation_temp_min' => 25.0,
                'incubation_temp_max' => 30.0,
                'incubation_hum_min' => 65.0,
                'incubation_hum_max' => 80.0,
                'temp_min' => 22.0,
                'temp_max' => 30.0,
                'hum_min' => 85.0,
                'hum_max' => 95.0,
                'incubation_days_min' => 14,
                'incubation_days_max' => 28,
                'pin_days_min' => 4,
                'pin_days_max' => 10,
                'harvest_days_min' => 5,
                'harvest_days_max' => 12,
            ],
        ];
    }

    public static function normalizeKey(?string $key): string
    {
        $key = (string) $key;
        $key = self::LEGACY_ALIASES[$key] ?? $key;

        return in_array($key, self::KEYS, true) ? $key : self::defaultKey();
    }

    public static function profile(string $key): ?array
    {
        $key = self::LEGACY_ALIASES[$key] ?? $key;

        return self::all()[$key] ?? null;
    }

    public static function defaultKey(): string
    {
        return 'oyster_white';
    }

    public static function isValidKey(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }
}
