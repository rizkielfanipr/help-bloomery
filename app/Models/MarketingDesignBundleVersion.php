<?php

namespace App\Models;

use App\Enums\MarketingDesignVersionStatus;
use Database\Factories\MarketingDesignBundleVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * One revision of a bundle (PRD §14.2). Published and superseded versions are immutable: the only
 * change allowed is the system moving a published version to superseded.
 */
class MarketingDesignBundleVersion extends Model
{
    /** @use HasFactory<MarketingDesignBundleVersionFactory> */
    use HasFactory;

    private const SUPERSEDE_COLUMNS = ['status', 'published_marker', 'superseded_at', 'updated_at'];

    protected $fillable = [
        'marketing_design_bundle_id',
        'version_number',
        'status',
        'published_marker',
        'cloned_from_version_id',
        'change_summary',
        'review_note',
        'publish_note',
        'created_by',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'published_by',
        'published_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MarketingDesignVersionStatus::class,
            'version_number' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            $original = MarketingDesignVersionStatus::from((string) $version->getRawOriginal('status'));

            if (! $original->isImmutable()) {
                return;
            }

            $isSuperseding = $original === MarketingDesignVersionStatus::Published
                && $version->status === MarketingDesignVersionStatus::Superseded
                && array_diff(array_keys($version->getDirty()), self::SUPERSEDE_COLUMNS) === [];

            if (! $isSuperseding) {
                throw new LogicException('Version published atau superseded tidak dapat diubah.');
            }
        });

        static::deleting(fn (): never => throw new LogicException('Version Master Design tidak dapat dihapus.'));
    }

    public function isDraft(): bool
    {
        return $this->status === MarketingDesignVersionStatus::Draft;
    }

    public function label(): string
    {
        return 'V'.$this->version_number;
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(MarketingDesignBundle::class, 'marketing_design_bundle_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketingDesignItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cloned_from_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
