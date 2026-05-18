<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrowSetting extends Model
{
    protected $table = 'grow_settings';

    protected $fillable = [
        'id',
        'mushroom_type',
        'incubation_started_at',
        'fruiting_started_at',
    ];

    protected function casts(): array
    {
        return [
            'incubation_started_at' => 'datetime',
            'fruiting_started_at' => 'datetime',
        ];
    }

    public static function singleton(): self
    {
        $row = static::query()->where('id', 1)->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'id' => 1,
            'mushroom_type' => 'oyster_mushroom',
            'incubation_started_at' => null,
            'fruiting_started_at' => null,
        ]);
    }
}
