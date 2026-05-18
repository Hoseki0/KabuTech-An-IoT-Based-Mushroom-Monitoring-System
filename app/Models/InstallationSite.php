<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallationSite extends Model
{
    protected $fillable = [
        'name',
        'owner_name',
        'location_description',
        'latitude',
        'longitude',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function activate(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            static::query()->update(['is_active' => false]);
            $this->is_active = true;
            $this->save();
        });
    }
}
