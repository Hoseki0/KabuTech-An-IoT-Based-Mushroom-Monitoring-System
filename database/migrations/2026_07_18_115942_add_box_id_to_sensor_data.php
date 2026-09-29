<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds a nullable box_id column so multiple ESP32 nodes can each
     * identify which physical incubator box they belong to.
     * Legacy rows (box_id = null) are treated as Box A.
     */
    public function up(): void
    {
        Schema::table('sensor_data', function (Blueprint $table) {
            $table->string('box_id', 32)->nullable()->default(null)->after('id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sensor_data', function (Blueprint $table) {
            $table->dropIndex(['box_id']);
            $table->dropColumn('box_id');
        });
    }
};
