<?php

namespace App\Models;

use Database\Factories\MarketingDesignItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * One design item with its production specification (PRD §14.3). The type vocabulary and the
 * physical/digital split reuse RndProjectMarketingMaterial so both features keep one set of labels.
 */
class MarketingDesignItem extends Model
{
    /** @use HasFactory<MarketingDesignItemFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'marketing_design_bundle_version_id',
        'cloned_from_item_id',
        'type',
        'title',
        'description',
        'sort_order',
        'dimensions',
        'material_specification',
        'finishing',
        'color_print_notes',
        'sides',
        'production_notes',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        $guard = function (self $item): void {
            if ($item->version()->first()?->status->isImmutable()) {
                throw new LogicException('Item pada version published atau superseded tidak dapat diubah.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
        static::forceDeleting(fn (): never => throw new LogicException('Item Master Design tidak dapat dihapus permanen.'));
    }

    public function isPhysical(): bool
    {
        return in_array($this->type, RndProjectMarketingMaterial::PHYSICAL_TYPES, true);
    }

    public function typeLabel(): string
    {
        return RndProjectMarketingMaterial::TYPES[$this->type] ?? $this->type;
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(MarketingDesignBundleVersion::class, 'marketing_design_bundle_version_id');
    }

    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cloned_from_item_id')->withTrashed();
    }

    public function files(): HasMany
    {
        return $this->hasMany(MarketingDesignFile::class);
    }
}
