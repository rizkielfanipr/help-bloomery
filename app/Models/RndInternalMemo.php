<?php

namespace App\Models;

use App\Enums\RndInternalMemoStatus;
use Database\Factories\RndInternalMemoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

/**
 * docs/rnd-internal-memo-prd.md §12.1, docs/rnd-internal-memo-brand-prd.md §8, §13. Company Code
 * is always BLSS for every new write and is set server-side; the column stays so legacy non-BLSS
 * rows remain readable. The Brand is business metadata only — it never selects a company, branch,
 * credential, or catalog. `brand_name_snapshot` keeps history stable when the Master Brand changes.
 */
class RndInternalMemo extends Model
{
    /** @use HasFactory<RndInternalMemoFactory> */
    use HasFactory;

    use SoftDeletes;

    public const COMPANY_CODE = 'BLSS';

    protected $fillable = [
        'company_code',
        'brand_id',
        'brand_name_snapshot',
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

    /**
     * `period_month_if_active` is a generated column that exists purely to make the
     * (brand_id, period_month, revision) uniqueness MySQL/SQLite-safe for soft deletes — see
     * its migrations. It has no application meaning and is never read or written by name.
     */
    protected $hidden = ['period_month_if_active'];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'memo_date' => 'date',
            'status' => RndInternalMemoStatus::class,
            'brand_id' => 'integer',
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

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Brand name for display: the snapshot first (history), the live relation only for transition
     * rows that have a Brand but no snapshot yet. Null means "Brand belum ditentukan".
     */
    public function brandLabel(): ?string
    {
        if (filled($this->brand_name_snapshot)) {
            return $this->brand_name_snapshot;
        }

        if ($this->brand_id === null) {
            return null;
        }

        $name = $this->brand?->name;
        if (filled($name)) {
            Log::info('rnd internal memo brand snapshot fallback used', ['memo_id' => $this->id]);
        }

        return $name;
    }

    public function hasBrand(): bool
    {
        return $this->brand_id !== null;
    }

    /**
     * Legacy multi-branch rows (docs/rnd-internal-memo-multi-branch-prd.md §9.1). Kept read-only for
     * the Brand backfill and audit during the compatibility window
     * (docs/rnd-internal-memo-brand-prd.md §13.4); no active flow reads or writes it.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(RndInternalMemoBranch::class);
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
     * The prior revision of this same monthly memo, if any: revisions share Brand + period_month
     * and are told apart only by `revision`. Legacy rows without a Brand fall back to company_code.
     */
    public function previousRevision(): ?self
    {
        if ($this->revision <= 1) {
            return null;
        }

        return static::query()
            ->when(
                $this->brand_id !== null,
                fn ($query) => $query->where('brand_id', $this->brand_id),
                fn ($query) => $query->whereNull('brand_id')->where('company_code', $this->company_code),
            )
            ->whereDate('period_month', $this->period_month)
            ->where('revision', $this->revision - 1)
            ->first();
    }
}
