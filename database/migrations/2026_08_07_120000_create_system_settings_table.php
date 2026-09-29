<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed defaults
        DB::table('system_settings')->insert([
            ['key' => 'retention_days',      'value' => '90',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'retention_last_run',  'value' => null,  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'retention_last_count','value' => null,  'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
