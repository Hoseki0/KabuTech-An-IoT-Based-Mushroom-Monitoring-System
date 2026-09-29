<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_code',
        'box_id',
        'mushroom_type',
        'stage',
        'bag_count',
        'inoculation_date',
        'fruiting_start_date',
        'expected_harvest_date',
        'notes',
        'status',
    ];

    protected $casts = [
        'bag_count' => 'integer',
        'inoculation_date' => 'date',
        'fruiting_start_date' => 'date',
        'expected_harvest_date' => 'date',
    ];
}
