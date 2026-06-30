<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CasualPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'fee_per_day',
        'overtime_rate_per_hour',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fee_per_day' => 'decimal:2',
            'overtime_rate_per_hour' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'casual_position_id');
    }

    public function openings(): HasMany
    {
        return $this->hasMany(CasualPositionOpening::class);
    }
}
