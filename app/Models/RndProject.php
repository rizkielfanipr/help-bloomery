<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RndProject extends Model
{
    protected $fillable = [
        'name',
        'description',
        'start_date',
        'end_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function boms(): HasMany
    {
        return $this->hasMany(RndProjectBom::class)->latest('updated_at');
    }

    public function products(): HasMany
    {
        return $this->hasMany(RndProjectProduct::class)->latest('updated_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
