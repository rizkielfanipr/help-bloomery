<?php

namespace App\Models;

use Database\Factories\MarketingDesignItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One design item with its production specification (PRD §14.2). The type vocabulary and the
 * physical/digital split reuse RndProjectMarketingMaterial so both features keep one set of labels.
 */
class MarketingDesignItem extends Model
{
    /** @use HasFactory<MarketingDesignItemFactory> */
    use HasFactory;

    protected $fillable = [
        'marketing_design_bundle_id',
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

    public function isPhysical(): bool
    {
        return in_array($this->type, RndProjectMarketingMaterial::PHYSICAL_TYPES, true);
    }

    public function typeLabel(): string
    {
        return RndProjectMarketingMaterial::TYPES[$this->type] ?? $this->type;
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(MarketingDesignBundle::class, 'marketing_design_bundle_id');
    }

    /** The current design file; a replaced file is soft deleted, so at most one stays active. */
    public function file(): HasOne
    {
        return $this->hasOne(MarketingDesignFile::class);
    }
}
