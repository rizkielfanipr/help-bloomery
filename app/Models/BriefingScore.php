<?php

namespace App\Models;

use App\Services\BriefingScoreResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefingScore extends Model
{
    protected $fillable = [
        'branch_id', 'year', 'month', 'score', 'breakdown', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'score' => 'float',
            'breakdown' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * docs/products/daily-briefing/scoring-ui/prd.md §8.4/§11: the passing score is read from this row's
     * own snapshot so a later settings change never changes a historical row's achieved/not status.
     * Rows computed before `passing_score` existed in the breakdown (method_version 1) fall back
     * to 80, the hardcoded threshold they were actually computed against.
     */
    public function isPassing(): bool
    {
        return $this->score >= ($this->breakdown['passing_score'] ?? 80);
    }

    public function status(): string
    {
        return $this->breakdown['status'] ?? ($this->isPassing() ? BriefingScoreResult::STATUS_ACHIEVED : BriefingScoreResult::STATUS_NOT_ACHIEVED);
    }

    public function isProvisional(): bool
    {
        return (bool) ($this->breakdown['is_provisional'] ?? false);
    }

    public function methodVersion(): int
    {
        return (int) ($this->breakdown['method_version'] ?? 1);
    }

    /** @return array<string, mixed>|null */
    public function periodBreakdown(string $period): ?array
    {
        return $this->breakdown['periods'][$period] ?? $this->breakdown[$period] ?? null;
    }

    public function monthLabel(): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return ($months[$this->month] ?? $this->month).' '.$this->year;
    }
}
