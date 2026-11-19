<?php

namespace Tests\Feature;

use App\Models\GrowSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GrowSettingsMlSummaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('grow_settings')) {
            Schema::create('grow_settings', function ($table) {
                $table->id();
                $table->string('mushroom_type')->default('oyster_white');
                $table->timestamp('incubation_started_at')->nullable();
                $table->timestamp('fruiting_started_at')->nullable();
                $table->timestamps();
            });
        }

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
                $table->float('temperature')->nullable();
                $table->float('humidity')->nullable();
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();
            });
        }

        GrowSetting::truncate();
    }

    public function test_grow_settings_returns_ml_summary_without_active_grow(): void
    {
        GrowSetting::create([
            'mushroom_type'         => 'oyster_white',
            'incubation_started_at' => null,
            'fruiting_started_at'   => null,
        ]);

        $response = $this->withoutMiddleware(\App\Http\Middleware\AuthenticateAnyGuard::class)
            ->getJson('/api/grow-settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'mushroom_type',
                'mushroom_label',
                'ml_summary' => [
                    'active_phase',
                    'growth_progress_percent',
                    'prediction_confidence_percent',
                    'model_fit_r2_percent',
                    'daily_growth_velocity',
                    'mae_days',
                    'sample_size',
                ],
            ])
            ->assertJson([
                'ml_summary' => [
                    'active_phase'            => 'none',
                    'growth_progress_percent' => 0,
                ],
            ]);
    }

    public function test_grow_settings_returns_fruiting_ml_summary_with_percentages(): void
    {
        GrowSetting::create([
            'mushroom_type'         => 'oyster_white',
            'incubation_started_at' => Carbon::now()->subDays(20),
            'fruiting_started_at'   => Carbon::now()->subDays(3),
        ]);

        $response = $this->withoutMiddleware(\App\Http\Middleware\AuthenticateAnyGuard::class)
            ->getJson('/api/grow-settings');

        $response->assertStatus(200);

        $json = $response->json();
        $this->assertEquals('fruiting', $json['ml_summary']['active_phase']);
        $this->assertGreaterThan(0, $json['ml_summary']['growth_progress_percent']);
        $this->assertLessThanOrEqual(100, $json['ml_summary']['growth_progress_percent']);
        $this->assertGreaterThan(0, $json['ml_summary']['prediction_confidence_percent']);
        $this->assertGreaterThan(0, $json['ml_summary']['model_fit_r2_percent']);
    }

    public function test_grow_settings_returns_incubation_ml_summary_with_percentages(): void
    {
        GrowSetting::create([
            'mushroom_type'         => 'oyster_white',
            'incubation_started_at' => Carbon::now()->subDays(7),
            'fruiting_started_at'   => null,
        ]);

        $response = $this->withoutMiddleware(\App\Http\Middleware\AuthenticateAnyGuard::class)
            ->getJson('/api/grow-settings');

        $response->assertStatus(200);

        $json = $response->json();
        $this->assertEquals('incubation', $json['ml_summary']['active_phase']);
        $this->assertGreaterThan(0, $json['ml_summary']['growth_progress_percent']);
        $this->assertLessThanOrEqual(100, $json['ml_summary']['growth_progress_percent']);
        $this->assertEquals(90, $json['ml_summary']['prediction_confidence_percent']);
    }
}
