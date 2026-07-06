<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelType extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
        'price_per_liter',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_per_liter' => 'integer',
        ];
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
