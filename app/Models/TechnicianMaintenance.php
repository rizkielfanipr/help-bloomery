<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TechnicianMaintenance extends Model
{
    use HasFactory;

    protected $fillable = ['maintenance_number', 'branch_id', 'technician_id', 'checked_at', 'maintenance_year', 'maintenance_month', 'status', 'score', 'earned_points', 'maximum_points', 'overall_notes', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_notes'];

    protected function casts(): array
    {
        return ['checked_at' => 'date', 'score' => 'float', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $maintenance): void {
            $maintenance->maintenance_number ??= 'TM-'.now()->format('Ym').'-'.Str::upper(Str::random(6));
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TechnicianMaintenanceItem::class)->orderBy('sort_order');
    }

    public function recalculateScore(): void
    {
        $answered = $this->items()->whereNotNull('result')->get();
        $maximum = $answered->sum('maximum_points');
        $earned = $answered->sum('earned_points');
        $this->forceFill(['maximum_points' => $maximum, 'earned_points' => $earned, 'score' => $maximum > 0 ? round(($earned / $maximum) * 100, 2) : 0])->saveQuietly();
    }
}
