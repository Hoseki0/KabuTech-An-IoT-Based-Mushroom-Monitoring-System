<?php

namespace App\Http\Controllers;

use App\Models\GrowSetting;
use App\Models\SensorData;
use App\Services\FruitingStartPredictionService;
use App\Services\HarvestPredictionService;
use App\Services\IncubationPredictionService;
use App\Services\MlHarvestPredictionModel;
use App\Services\MushroomSpeciesCatalog;
use Carbon\Carbon;
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

        $incubationPred = IncubationPredictionService::predict($settings->incubation_started_at, $key);
        $harvestPred    = HarvestPredictionService::predict($settings->fruiting_started_at, $key);
        $fruitingStartPred = $settings->fruiting_started_at ? null : FruitingStartPredictionService::predict($key);

        $trainedMetrics = MlHarvestPredictionModel::trainOrFit($key);
        $modelFitPct    = (int) round(($trainedMetrics['r_squared'] ?? 0.86) * 100);

        $activePhase     = 'none';
        $growthProgress  = 0;
        $confidenceScore = 85;
        $dailyVelocity   = 1.0;

        if ($harvestPred !== null) {
            $activePhase     = 'fruiting';
            $growthProgress  = (int) ($harvestPred['growth_progress_percent'] ?? 0);
            $confidenceScore = (int) ($harvestPred['confidence_score'] ?? 85);
            $dailyVelocity   = (float) ($harvestPred['daily_growth_velocity'] ?? 1.0);
        } elseif ($incubationPred !== null) {
            $activePhase     = 'incubation';
            $growthProgress  = (int) ($incubationPred['growth_progress_percent'] ?? 0);
            $confidenceScore = (int) ($incubationPred['confidence_score'] ?? 90);
            $dailyVelocity   = 1.0;
        }

        // Environmental Efficiency Metrics (Temperature, Humidity, Combined)
        $tempEfficiency = null;
        $humEfficiency  = null;
        $envEfficiency  = null;

        if ($harvestPred !== null && isset($harvestPred['temp_efficiency_percent'])) {
            $tempEfficiency = (float) $harvestPred['temp_efficiency_percent'];
            $humEfficiency  = (float) $harvestPred['hum_efficiency_percent'];
            $envEfficiency  = (float) $harvestPred['climate_efficiency_percent'];
        } elseif ($latest && $latest->temperature !== null && $latest->humidity !== null) {
            $optTemp   = ($profile['temp_min'] + $profile['temp_max']) / 2.0;
            $tempRange = max(1.0, ($profile['temp_max'] - $profile['temp_min']) / 2.0);
            $currTemp  = (float) $latest->temperature;
            $currHum   = (float) $latest->humidity;

            if ($currTemp >= $profile['temp_min'] && $currTemp <= $profile['temp_max']) {
                $tempEfficiency = round(100.0 - (abs($currTemp - $optTemp) / $tempRange) * 15.0, 1);
            } else {
                $dist = ($currTemp < $profile['temp_min']) ? ($profile['temp_min'] - $currTemp) : ($currTemp - $profile['temp_max']);
                $tempEfficiency = max(10.0, round(85.0 - ($dist * 12.0), 1));
            }

            if ($currHum >= $profile['hum_min'] && $currHum <= $profile['hum_max']) {
                $optHum = ($profile['hum_min'] + $profile['hum_max']) / 2.0;
                $humEfficiency = round(100.0 - (abs($currHum - $optHum) / max(1.0, ($profile['hum_max'] - $profile['hum_min']) / 2.0)) * 10.0, 1);
            } elseif ($currHum < $profile['hum_min']) {
                $humEfficiency = max(10.0, round(90.0 - (($profile['hum_min'] - $currHum) * 3.5), 1));
            } else {
                $humEfficiency = max(70.0, round(95.0 - (($currHum - $profile['hum_max']) * 2.0), 1));
            }
            $envEfficiency = round(($tempEfficiency * 0.5) + ($humEfficiency * 0.5), 1);
        }

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
            'incubation_prediction' => $incubationPred,
            'fruiting_started_at' => $settings->fruiting_started_at?->toIso8601String(),
            'fruiting_start_prediction' => $fruitingStartPred,
            'prediction' => $harvestPred,
            'environment' => $env,
            'ml_summary' => [
                'active_phase'                  => $activePhase,
                'growth_progress_percent'       => $growthProgress,
                'prediction_confidence_percent' => $confidenceScore,
                'model_fit_r2_percent'          => $modelFitPct,
                'daily_growth_velocity'         => $dailyVelocity,
                'mae_days'                      => $trainedMetrics['mae_days'] ?? 0.45,
                'sample_size'                   => $trainedMetrics['sample_size'] ?? 0,
                'temp_efficiency_percent'       => $tempEfficiency,
                'hum_efficiency_percent'        => $humEfficiency,
                'climate_efficiency_percent'    => $envEfficiency,
            ],
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

    /**
     * On-demand Machine Learning Harvest Prediction API endpoint.
     */
    public function getHarvestPrediction(Request $request): JsonResponse
    {
        $settings = GrowSetting::singleton();
        $mushroomKey = $request->query('mushroom_type', $settings->mushroom_type);
        if (!MushroomSpeciesCatalog::isValidKey($mushroomKey)) {
            $mushroomKey = MushroomSpeciesCatalog::defaultKey();
        }

        $fruitingStartedAt = null;
        if ($request->has('fruiting_started_at')) {
            $fruitingStartedAt = Carbon::parse($request->query('fruiting_started_at'));
        } elseif ($settings->fruiting_started_at) {
            $fruitingStartedAt = $settings->fruiting_started_at;
        }

        if (!$fruitingStartedAt) {
            return response()->json([
                'success' => false,
                'message' => 'Fruiting start date not set. Provide fruiting_started_at query parameter or start fruiting in settings.',
                'prediction' => null,
            ]);
        }

        $boxId = $request->query('box_id');
        $flushNumber = max(1, (int) $request->query('flush_number', 1));
        $substrateWeight = $request->has('substrate_weight_grams') ? (float) $request->query('substrate_weight_grams') : null;

        $prediction = MlHarvestPredictionModel::predict(
            $fruitingStartedAt,
            $mushroomKey,
            $boxId,
            $flushNumber,
            $substrateWeight
        );

        return response()->json([
            'success' => true,
            'prediction' => $prediction,
        ]);
    }

    /**
     * Machine Learning Model Metadata & Training Statistics.
     */
    public function getModelMetrics(Request $request): JsonResponse
    {
        if ($request->has('refresh') && $request->boolean('refresh')) {
            MlHarvestPredictionModel::clearCache();
        }

        $whiteMetrics = MlHarvestPredictionModel::trainOrFit('oyster_white');
        $brownMetrics = MlHarvestPredictionModel::trainOrFit('oyster_brown');

        return response()->json([
            'success' => true,
            'models' => [
                'oyster_white' => $whiteMetrics,
                'oyster_brown' => $brownMetrics,
            ],
            'base_temp_celsius' => MlHarvestPredictionModel::BASE_TEMP_CELSIUS,
            'ridge_lambda' => MlHarvestPredictionModel::RIDGE_LAMBDA,
            'description' => 'Thermal-Time Growth Degree Day & Environmental Stress Multi-variable Ridge Regression Model',
        ]);
    }
}
