<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MistingControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('misting_control')) {
            Schema::create('misting_control', function ($table) {
                $table->id();
                $table->boolean('desired_on')->default(false);
                $table->string('desired_mode', 16)->default('auto');
                $table->string('desired_profile', 32)->default('fruiting');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('fan_control')) {
            Schema::create('fan_control', function ($table) {
                $table->id();
                $table->boolean('desired_on')->default(false);
                $table->string('desired_mode', 16)->default('auto');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('grow_settings')) {
            Schema::create('grow_settings', function ($table) {
                $table->id();
                $table->string('mushroom_type')->default('oyster_white');
                $table->timestamp('incubation_started_at')->nullable();
                $table->timestamp('fruiting_started_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_can_switch_misting_mode_to_auto(): void
    {
        $response = $this->postJson('/api/misting/control', [
            'mode' => 'auto',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'      => true,
                'desired_mode' => 'auto',
                'desired_on'   => false,
            ]);
    }

    public function test_can_switch_misting_mode_to_manual(): void
    {
        $response = $this->postJson('/api/misting/control', [
            'mode' => 'manual',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'      => true,
                'desired_mode' => 'manual',
            ]);
    }

    public function test_can_toggle_misting_pump_in_manual_mode(): void
    {
        // Turn ON
        $responseOn = $this->postJson('/api/misting/control', [
            'mode'   => 'manual',
            'status' => true,
        ]);

        $responseOn->assertStatus(200)
            ->assertJson([
                'success'      => true,
                'desired_mode' => 'manual',
                'desired_on'   => true,
            ]);

        // Turn OFF
        $responseOff = $this->postJson('/api/misting/control', [
            'mode'   => 'manual',
            'status' => false,
        ]);

        $responseOff->assertStatus(200)
            ->assertJson([
                'success'      => true,
                'desired_mode' => 'manual',
                'desired_on'   => false,
            ]);
    }

    public function test_get_misting_status_returns_current_mode_and_state(): void
    {
        $this->postJson('/api/misting/control', [
            'mode' => 'auto',
        ]);

        $response = $this->getJson('/api/misting/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'desired_on',
                'desired_mode',
                'desired_profile',
                'targets',
            ])
            ->assertJson([
                'desired_mode' => 'auto',
                'desired_on'   => false,
            ]);
    }
}
