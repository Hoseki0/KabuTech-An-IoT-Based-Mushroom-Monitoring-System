<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorData extends Model
{
    protected $fillable = [
        'temperature',
        'humidity',
        'wifi_rssi',
        'misting_system',
        'misting_source',
        'misting_reason',
        'misting_total_ms',
        'misting_last_burst_ms',
        'recorded_at',
    ];

    protected $casts = [
        'temperature' => 'decimal:2',
        'humidity' => 'decimal:2',
        'misting_system' => 'boolean',
        'recorded_at' => 'datetime',
    ];
}
