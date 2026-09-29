<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a composite index on (recorded_at, box_id) so that:
     *  - ORDER BY recorded_at DESC queries use an index scan
     *  - WHERE box_id = ? ORDER BY recorded_at DESC also benefits
     */
    public function up(): void
    {
        Schema::table('sensor_data', function (Blueprint $table) {
            // Composite covers both "latest overall" and "latest per box" queries
            $table->index(['recorded_at', 'box_id'], 'sensor_data_recorded_at_box_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sensor_data', function (Blueprint $table) {
            $table->dropIndex('sensor_data_recorded_at_box_id_idx');
        });
    }
};
