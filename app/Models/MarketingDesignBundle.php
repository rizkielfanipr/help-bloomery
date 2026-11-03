<?php

namespace App\Models;

use App\Enums\MarketingDesignBundleCategory;
use App\Enums\MarketingDesignBundleStatus;
use App\Policies\MarketingDesignBundlePolicy;
use Database\Factories\MarketingDesignBundleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * One Brand-owned design bundle (docs/products/brand-marketing/design-master-library/prd.md §14.1).
 * Bundles are archived, never deleted.
 */
class MarketingDesignBundle extends Model
{
    /** @use HasFactory<MarketingDesignBundleFactory> */
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'code',
        'normalized_code',
        'name',
        'category',
        'description',
        'status',
        'created_by',
        'updated_by',
        'archived_by',
        'archived_at',
        'archive_reason',
    ];

    protected function casts(): array
    {
        return [
            'category' => MarketingDesignBundleCategory::class,
            'status' => MarketingDesignBundleStatus::class,
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new LogicException('Bundle Master Design tidak dapat dihapus; gunakan arsip.'));
    }

    public static function normalizeCode(string $code): string
    {
        return Str::of($code)->trim()->upper()->replaceMatches('/\s+/', '-')->toString();
    }

    public function isActive(): bool
    {
        return $this->status === MarketingDesignBundleStatus::Active;
    }

    /** Internal users see every bundle; everyone else only active bundles. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (app(MarketingDesignBundlePolicy::class)->viewArchived($user)) {
            return $query;
        }

        return $query->where('status', MarketingDesignBundleStatus::Active);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketingDesignItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(MarketingDesignAuditLog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
}
