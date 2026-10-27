<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\BriefingBranchSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefingBranchSetting extends Model
{
    /** @use HasFactory<BriefingBranchSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'is_enabled',
        'started_at',
        'ended_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * docs/products/daily-briefing/remediation/prd.md §8.2: a branch is an active participant on date $date
     * when an enabled row covers it.
     */
    public function coversDate(CarbonInterface $date): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        if ($this->started_at->gt($date->toImmutable())) {
            return false;
        }

        return $this->ended_at === null || $this->ended_at->gte($date->toImmutable());
    }
}
