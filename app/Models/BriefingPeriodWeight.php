<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefingPeriodWeight extends Model
{
    protected $fillable = [
        'branch_id',
        'daily_weight',
        'weekly_weight',
        'monthly_weight',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'daily_weight' => 'float',
            'weekly_weight' => 'float',
            'monthly_weight' => 'float',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Resolve the effective period weights for a branch: a branch-specific
     * override if one exists, otherwise the global default row (branch_id
     * null, always seeded by the create-table migration).
     *
     * @return array{daily: float, weekly: float, monthly: float}
     */
    public static function forBranch(?int $branchId): array
    {
        $row = $branchId !== null
            ? static::query()->where('branch_id', $branchId)->first()
            : null;

        $row ??= static::query()->whereNull('branch_id')->first();

        return [
            'daily' => (float) ($row?->daily_weight ?? 40),
            'weekly' => (float) ($row?->weekly_weight ?? 30),
            'monthly' => (float) ($row?->monthly_weight ?? 30),
        ];
    }
}
