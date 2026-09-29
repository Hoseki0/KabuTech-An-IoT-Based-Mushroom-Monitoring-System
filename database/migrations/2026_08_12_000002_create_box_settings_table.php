<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('box_settings', function (Blueprint $table) {
            $table->id();
            $table->string('box_id', 32)->unique();
            $table->string('name', 128);
            $table->string('mushroom_type', 64)->default('oyster_white');
            $table->enum('stage', ['incubation', 'fruiting'])->default('fruiting');
            $table->decimal('temp_min', 5, 2)->nullable();
            $table->decimal('temp_max', 5, 2)->nullable();
            $table->decimal('hum_min', 5, 2)->nullable();
            $table->decimal('hum_max', 5, 2)->nullable();
            $table->enum('misting_mode', ['auto', 'manual'])->default('auto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('box_settings');
    }
};
