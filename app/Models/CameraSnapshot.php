<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CameraSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'box_id',
        'image_path',
        'notes',
        'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
    ];
}
