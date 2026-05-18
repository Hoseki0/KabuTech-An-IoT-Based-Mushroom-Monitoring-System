<?php

namespace App\Http\Controllers;

use App\Models\GrowSetting;
use App\Models\SensorData;
use App\Services\FruitingStartPredictionService;
use App\Services\HarvestPredictionService;
use App\Services\IncubationPredictionService;
use App\Services\MushroomSpeciesCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrowSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = GrowSetting::singleton();
        $key = MushroomSpeciesCatalog::isValidKey($settings->mushroom_type)
            ? $settings->mushroom_type
            : MushroomSpeciesCatalog::defaultKey();
        $profile = MushroomSpeciesCatalog::profile($key);

        $latest = SensorData::latest('recorded_at')->first();
        $env = $this->environmentStatus($latest, $profile);

        return response()->json([
            'mushroom_type' => $key,
            'mushroom_label' => $profile['label'],
            'incubation_targets' => [
                'temp_min' => $profile['incubation_temp_min'] ?? null,
                'temp_max' => $profile['incubation_temp_max'] ?? null,
                'hum_min' => $profile['incubation_hum_min'] ?? null,
                'hum_max' => $profile['incubation_hum_max'] ?? null,
            ],
            'targets' => [
                'temp_min' => $profile['temp_min'],
                'temp_max' => $profile['temp_max'],
                'hum_min' => $profile['hum_min'],
                'hum_max' => $profile['hum_max'],
            ],
            'incubation_started_at' => $settings->incubation_started_at?->toIso8601String(),
            'incubation_prediction' => IncubationPredictionService::predict($settings->incubation_started_at, $key),
            'fruiting_started_at' => $settings->fruiting_started_at?->toIso8601String(),
            'fruiting_start_prediction' => $settings->fruiting_started_at ? null : FruitingStartPredictionService::predict($key),
            'prediction' => HarvestPredictionService::predict($settings->fruiting_started_at, $key),
            'environment' => $env,
            'latest' => $latest ? [
                'temperature' => $latest->temperature !== null ? (float) $latest->temperature : null,
                'humidity' => $latest->humidity !== null ? (float) $latest->humidity : null,
                'recorded_at' => $latest->recorded_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mushroom_type' => 'sometimes|string|in:'.implode(',', MushroomSpeciesCatalog::KEYS),
            'incubation_started_at' => 'nullable|date',
            'fruiting_started_at' => 'nullable|date',
            'clear_incubation' => 'sometimes|boolean',
            'clear_fruiting' => 'sometimes|boolean',
        ]);

        $settings = GrowSetting::singleton();

        if (array_key_exists('mushroom_type', $validated)) {
            $settings->mushroom_type = $validated['mushroom_type'];
        }

        if (! empty($validated['clear_incubation'])) {
            $settings->incubation_started_at = null;
        } elseif (array_key_exists('incubation_started_at', $validated)) {
            $settings->incubation_started_at = $validated['incubation_started_at'];
        }

        if (! empty($validated['clear_fruiting'])) {
            $settings->fruiting_started_at = null;
        } elseif (array_key_exists('fruiting_started_at', $validated)) {
            $settings->fruiting_started_at = $validated['fruiting_started_at'];
        }

        $settings->save();

        return $this->show();
    }

    /**
     * @param  array<string, mixed>|null  $profile
     * @return array{status: string, messages: list<string>}
     */
    private function environmentStatus(?SensorData $latest, ?array $profile): array
    {
        if ($profile === null || $latest === null) {
            return ['status' => 'unknown', 'messages' => []];
        }

        $messages = [];
        $t = $latest->temperature !== null ? (float) $latest->temperature : null;
        $h = $latest->humidity !== null ? (float) $latest->humidity : null;

        if ($t !== null) {
            if ($t < $profile['temp_min']) {
                $messages[] = 'Temperature is below the ideal range — warm the grow area if possible.';
            } elseif ($t > $profile['temp_max']) {
                $messages[] = 'Temperature is above the ideal range — improve airflow or cooling.';
            }
        }
        if ($h !== null) {
            if ($h < $profile['hum_min']) {
                $messages[] = 'Humidity is below target — AUTO misting will help reach '.$profile['label'].' needs.';
            } elseif ($h > $profile['hum_max']) {
                $messages[] = 'Humidity is very high — ensure fresh air exchange (FAE) to avoid stagnation.';
            }
        }

        if ($t !== null && $h !== null
            && $t >= $profile['temp_min'] && $t <= $profile['temp_max']
            && $h >= $profile['hum_min'] && $h <= $profile['hum_max']) {
            return ['status' => 'optimal', 'messages' => ['Conditions are within the ideal band for '.$profile['label'].'.']];
        }

        return ['status' => count($messages) ? 'attention' : 'unknown', 'messages' => $messages];
    }
}
