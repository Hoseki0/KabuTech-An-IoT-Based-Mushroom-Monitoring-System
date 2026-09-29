<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoxSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'box_id',
        'name',
        'mushroom_type',
        'stage',
        'temp_min',
        'temp_max',
        'hum_min',
        'hum_max',
        'misting_mode',
    ];

    protected $casts = [
        'temp_min' => 'decimal:2',
        'temp_max' => 'decimal:2',
        'hum_min' => 'decimal:2',
        'hum_max' => 'decimal:2',
    ];
}
