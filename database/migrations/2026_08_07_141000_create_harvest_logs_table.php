<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvest_logs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id', 64)->nullable();
            $table->string('batch_name', 128);
            $table->string('mushroom_type', 64);
            $table->string('incubator', 32)->nullable()->default('incubator_a');
            $table->unsignedInteger('flush_number')->default(1);
            $table->decimal('weight_grams', 10, 2);
            $table->decimal('substrate_weight_grams', 10, 2)->nullable();
            $table->decimal('biological_efficiency', 6, 2)->nullable();
            $table->string('quality', 32)->default('Grade A');
            $table->text('notes')->nullable();
            $table->timestamp('harvested_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvest_logs');
    }
};
