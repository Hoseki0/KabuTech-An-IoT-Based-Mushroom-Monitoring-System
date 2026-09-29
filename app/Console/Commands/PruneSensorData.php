<?php

namespace App\Console\Commands;

use App\Models\SensorData;
use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneSensorData extends Command
{
    protected $signature   = 'sensor:prune {--days= : Override retention days for this run}';
    protected $description = 'Delete sensor_data rows older than the configured retention period.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: SystemSetting::get('retention_days', 90));

        if ($days <= 0) {
            $this->warn('Retention days must be a positive integer. Skipping.');
            return self::FAILURE;
        }

        $cutoff   = Carbon::now()->subDays($days);
        $deleted  = SensorData::where('recorded_at', '<', $cutoff)->delete();

        // Persist run metadata
        SystemSetting::set('retention_last_run',   now()->toIso8601String());
        SystemSetting::set('retention_last_count',  (string) $deleted);

        $this->info("Pruned {$deleted} sensor row(s) older than {$days} days (cutoff: {$cutoff->toDateTimeString()}).");
        return self::SUCCESS;
    }
}
