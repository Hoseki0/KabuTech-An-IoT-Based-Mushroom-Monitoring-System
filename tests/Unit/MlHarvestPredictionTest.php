<?php

namespace Tests\Unit;

use App\Services\HarvestPredictionService;
use App\Services\MlHarvestPredictionModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MlHarvestPredictionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('harvest_logs')) {
            Schema::create('harvest_logs', function ($table) {
                $table->id();
                $table->string('batch_id')->nullable();
                $table->string('batch_name')->nullable();
                $table->string('mushroom_type');
                $table->string('incubator')->nullable();
                $table->integer('flush_number')->default(1);
                $table->decimal('weight_grams', 8, 2)->default(0);
                $table->decimal('substrate_weight_grams', 8, 2)->nullable();
                $table->decimal('biological_efficiency', 5, 2)->nullable();
                $table->string('quality')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('harvested_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sensor_data')) {
            Schema::create('sensor_data', function ($table) {
                $table->id();
                $table->string('box_id')->nullable();
                $table->decimal('temperature', 5, 2)->nullable();
                $table->decimal('humidity', 5, 2)->nullable();
                $table->boolean('misting_system')->default(false);
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_train_or_fit_returns_valid_model_parameters(): void
    {
        $white = MlHarvestPredictionModel::trainOrFit('oyster_white');
        $this->assertArrayHasKey('intercept_days', $white);
        $this->assertArrayHasKey('coef_temp_deviation', $white);
        $this->assertArrayHasKey('coef_hum_deficit', $white);
        $this->assertArrayHasKey('r_squared', $white);
        $this->assertGreaterThan(0.5, $white['r_squared']);
        $this->assertLessThanOrEqual(1.0, $white['r_squared']);
        $this->assertGreaterThan(0, $white['intercept_days']);

        $brown = MlHarvestPredictionModel::trainOrFit('oyster_brown');
        $this->assertGreaterThan(0, $brown['intercept_days']);
        $this->assertArrayHasKey('model_type', $brown);
    }

    public function test_predict_calculates_sensible_growth_metrics(): void
    {
        $fruitingStart = Carbon::now()->subDays(3);
        $pred = MlHarvestPredictionModel::predict(
            $fruitingStart,
            'oyster_white',
            null,
            1,
            1000.0 // 1000g substrate
        );

        $this->assertNotNull($pred);
        $this->assertEquals($fruitingStart->toIso8601String(), $pred['fruiting_started_at']);
        $this->assertArrayHasKey('predicted_harvest_at', $pred);
        $this->assertArrayHasKey('days_remaining', $pred);
        $this->assertArrayHasKey('growth_progress_percent', $pred);
        $this->assertArrayHasKey('daily_growth_velocity', $pred);
        $this->assertArrayHasKey('confidence_score', $pred);

        $this->assertGreaterThanOrEqual(0, $pred['growth_progress_percent']);
        $this->assertLessThanOrEqual(100, $pred['growth_progress_percent']);

        // Yield prediction
        $this->assertNotNull($pred['yield_prediction']['predicted_yield_grams']);
        $this->assertGreaterThan(200.0, $pred['yield_prediction']['predicted_yield_grams']);
    }

    public function test_harvest_prediction_service_delegates_to_ml_model(): void
    {
        $fruitingStart = Carbon::now()->subDays(2);
        $serviceResult = HarvestPredictionService::predict($fruitingStart, 'oyster_white');

        $this->assertNotNull($serviceResult);
        $this->assertArrayHasKey('earliest_harvest_at', $serviceResult);
        $this->assertArrayHasKey('latest_harvest_at', $serviceResult);
        $this->assertArrayHasKey('predicted_harvest_at', $serviceResult);
        $this->assertArrayHasKey('growth_progress_percent', $serviceResult);
        $this->assertArrayHasKey('confidence_score', $serviceResult);
        $this->assertArrayHasKey('days_until_earliest', $serviceResult);
        $this->assertArrayHasKey('note', $serviceResult);
    }

    public function test_incubation_prediction_calculates_growth_progress_percent(): void
    {
        $incubationStart = Carbon::now()->subDays(7);
        $result = \App\Services\IncubationPredictionService::predict($incubationStart, 'oyster_white');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('growth_progress_percent', $result);
        $this->assertArrayHasKey('confidence_score', $result);
        $this->assertGreaterThan(0, $result['growth_progress_percent']);
        $this->assertLessThanOrEqual(100, $result['growth_progress_percent']);
        $this->assertEquals(90, $result['confidence_score']);
    }
}
