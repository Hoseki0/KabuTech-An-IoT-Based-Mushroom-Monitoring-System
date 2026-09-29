<?php

use App\Console\Commands\PruneSensorData;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-prune old sensor data every day at 02:00
Schedule::command(PruneSensorData::class)->dailyAt('02:00')->withoutOverlapping();
