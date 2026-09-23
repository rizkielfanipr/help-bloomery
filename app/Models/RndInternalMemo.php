<?php

namespace App\Models;

use App\Enums\RndInternalMemoStatus;
use Database\Factories\RndInternalMemoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * docs/rnd-internal-memo-prd.md §12.1. Company Code is always BLSS; the value is stored so the
 * schema is explicit, not because another Company Code is selectable.
 */
class RndInternalMemo extends Model
{
    /** @use HasFactory<RndInternalMemoFactory> */
    use HasFactory;

    use SoftDeletes;

    public const COMPANY_CODE = 'BLSS';

    protected $fillable = [
        'company_code',
        'memo_number',
        'title',
        'period_month',
        'memo_date',
        'recipient',
        'sender',
        'subject',
        'notes',
        'status',
        'revision',
        'source_synced_at',
        'snapshot_hash',
        'created_by',
        'updated_by',
        'finalized_by',
        'finalized_at',
        'archived_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'memo_date' => 'date',
            'status' => RndInternalMemoStatus::class,
            'revision' => 'integer',
            'source_synced_at' => 'datetime',
            'finalized_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function menus(): HasMany
    {
        return $this->hasMany(RndInternalMemoMenu::class)->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RndInternalMemoDocument::class)->latest('revision');
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(RndInternalMemoSyncRun::class)->latest();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * The prior revision of this same monthly memo, if any (§12.1: revisions share
     * company_code + period_month and are told apart only by `revision`).
     */
    public function previousRevision(): ?self
    {
        if ($this->revision <= 1) {
            return null;
        }

        return static::query()
            ->where('company_code', $this->company_code)
            ->whereDate('period_month', $this->period_month)
            ->where('revision', $this->revision - 1)
            ->first();
    }
}
