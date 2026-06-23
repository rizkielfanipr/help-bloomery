<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CasualPositionOpening extends Model
{
    use HasFactory;

    protected $fillable = [
        'casual_position_id',
        'branch_id',
        'work_date',
        'total_slots',
        'description',
        'is_active',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'is_active' => 'boolean',
            'total_slots' => 'integer',
        ];
    }

    public function casualPosition(): BelongsTo
    {
        return $this->belongsTo(CasualPosition::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(CasualPositionRegistration::class);
    }

    public function getRemainingSlots(): int
    {
        return max(0, $this->total_slots - $this->registrations()->count());
    }

    public function isFull(): bool
    {
        return $this->registrations()->count() >= $this->total_slots;
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('work_date', '>=', today())
            ->orderBy('work_date')
            ->orderBy('created_at');
    }
}
