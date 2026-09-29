<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensor_data', function (Blueprint $table) {
            // Track whether the fan relay was active when this reading was recorded
            $table->boolean('fan_system')->default(false)->after('misting_last_burst_ms');
        });

        // Add fan_control table for dashboard manual/auto override (mirrors misting_control)
        Schema::create('fan_control', function (Blueprint $table) {
            $table->id();
            $table->boolean('desired_on')->default(false);
            $table->string('desired_mode', 16)->default('auto'); // auto | manual
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('sensor_data', function (Blueprint $table) {
            $table->dropColumn('fan_system');
        });
        Schema::dropIfExists('fan_control');
    }
};
