<?php

namespace App\Console\Commands;

use App\Services\DeviceHealthService;
use Illuminate\Console\Command;

class CheckDeviceHealth extends Command
{
    protected $signature   = 'device:health-check';
    protected $description = 'Check ESP32 / SHT31 / relays / ESP32-CAM and notify users about hardware problems.';

    public function handle(DeviceHealthService $health): int
    {
        $status = $health->check(includeCamera: true);

        foreach ($status as $key => $failing) {
            $this->line(sprintf('  %-16s %s', $key, $failing ? '<fg=red>PROBLEM</>' : '<fg=green>OK</>'));
        }

        return self::SUCCESS;
    }
}
