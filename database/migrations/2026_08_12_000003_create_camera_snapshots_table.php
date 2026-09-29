<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camera_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('box_id', 32)->default('box_a');
            $table->string('image_path', 255);
            $table->text('notes')->nullable();
            $table->timestamp('captured_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camera_snapshots');
    }
};
