<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 64)->unique();
            $table->string('box_id', 32)->default('box_a');
            $table->string('mushroom_type', 64)->default('oyster_white');
            $table->enum('stage', ['inoculation', 'incubation', 'fruiting', 'spent'])->default('incubation');
            $table->unsignedInteger('bag_count')->default(100);
            $table->date('inoculation_date')->nullable();
            $table->date('fruiting_start_date')->nullable();
            $table->date('expected_harvest_date')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'archived', 'completed'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
