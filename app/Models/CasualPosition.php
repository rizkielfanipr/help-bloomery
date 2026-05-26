<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CasualPosition extends Model
{
    protected $fillable = [
        'name',
        'fee_per_day',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fee_per_day' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'casual_position_id');
    }
}
