<?php

namespace App\Services;

/**
 * Fruiting-stage targets (approximate, educational).
 * Harvest window = typical days after pinning / fruiting start (wide ranges).
 */
final class MushroomSpeciesCatalog
{
    public const KEYS = [
        'oyster_mushroom',
        'straw_mushroom',
        'milky_mushroom',
        'wood_ear',
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
            'oyster_mushroom' => [
                'label' => 'Oyster Mushroom',
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
            'straw_mushroom' => [
                'label' => 'Straw Mushroom',
                'incubation_temp_min' => 30.0,
                'incubation_temp_max' => 35.0,
                'incubation_hum_min' => 70.0,
                'incubation_hum_max' => 85.0,
                'temp_min' => 28.0,
                'temp_max' => 35.0,
                'hum_min' => 85.0,
                'hum_max' => 95.0,
                'incubation_days_min' => 7,
                'incubation_days_max' => 14,
                'pin_days_min' => 4,
                'pin_days_max' => 9,
                'harvest_days_min' => 7,
                'harvest_days_max' => 12,
            ],
            'milky_mushroom' => [
                'label' => 'Milky Mushroom',
                'incubation_temp_min' => 25.0,
                'incubation_temp_max' => 32.0,
                'incubation_hum_min' => 65.0,
                'incubation_hum_max' => 80.0,
                'temp_min' => 22.0,
                'temp_max' => 30.0,
                'hum_min' => 80.0,
                'hum_max' => 92.0,
                'incubation_days_min' => 10,
                'incubation_days_max' => 18,
                'pin_days_min' => 4,
                'pin_days_max' => 10,
                'harvest_days_min' => 6,
                'harvest_days_max' => 14,
            ],
            'wood_ear' => [
                'label' => 'Wood Ear',
                'incubation_temp_min' => 24.0,
                'incubation_temp_max' => 30.0,
                'incubation_hum_min' => 70.0,
                'incubation_hum_max' => 85.0,
                'temp_min' => 20.0,
                'temp_max' => 28.0,
                'hum_min' => 85.0,
                'hum_max' => 95.0,
                'incubation_days_min' => 12,
                'incubation_days_max' => 28,
                'pin_days_min' => 5,
                'pin_days_max' => 12,
                'harvest_days_min' => 5,
                'harvest_days_max' => 10,
            ],
        ];
    }

    public static function profile(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function defaultKey(): string
    {
        return 'oyster_mushroom';
    }

    public static function isValidKey(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }
}
