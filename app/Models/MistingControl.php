<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MistingControl extends Model
{
    protected $table = 'misting_control';

    protected $fillable = [
        'desired_on',
        'desired_mode',
        'desired_profile',
    ];

    protected $casts = [
        'desired_on' => 'boolean',
    ];
}
