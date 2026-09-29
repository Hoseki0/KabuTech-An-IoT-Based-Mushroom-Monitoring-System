<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarvestLog extends Model
{
    protected $fillable = [
        'batch_id',
        'batch_name',
        'mushroom_type',
        'incubator',
        'flush_number',
        'weight_grams',
        'substrate_weight_grams',
        'biological_efficiency',
        'quality',
        'notes',
        'harvested_at',
    ];

    protected $casts = [
        'flush_number' => 'integer',
        'weight_grams' => 'float',
        'substrate_weight_grams' => 'float',
        'biological_efficiency' => 'float',
        'harvested_at' => 'datetime',
    ];
}
