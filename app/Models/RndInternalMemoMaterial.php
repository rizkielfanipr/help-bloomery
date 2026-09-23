<?php

namespace App\Models;

use Database\Factories\RndInternalMemoMaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/rnd-internal-memo-prd.md §12.3, simplified on explicit user instruction: no waste,
 * tolerance, or gross quantity columns — only the material and its quantity. `net_quantity`
 * is the resolved requirement for the memo's forecast (quantity_per_menu multiplied through
 * every Assembly level it passed through), mirroring the existing production algorithm in
 * App\Services\RndProjectMaterialForecastService.
 */
class RndInternalMemoMaterial extends Model
{
    /** @use HasFactory<RndInternalMemoMaterialFactory> */
    use HasFactory;

    protected $fillable = [
        'rnd_internal_memo_menu_id',
        'parent_material_id',
        'source_bom_id',
        'source_bom_code',
        'source_path',
        'depth',
        'esb_product_id',
        'esb_product_detail_id',
        'product_code',
        'product_name',
        'category_name',
        'uom_id',
        'uom_name',
        'quantity_per_menu',
        'net_quantity',
        'is_wip',
        'is_packaging',
        'product_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'source_path' => 'array',
            'depth' => 'integer',
            'quantity_per_menu' => 'decimal:4',
            'net_quantity' => 'decimal:4',
            'is_wip' => 'boolean',
            'is_packaging' => 'boolean',
            'product_snapshot' => 'array',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(RndInternalMemoMenu::class, 'rnd_internal_memo_menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_material_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_material_id');
    }

    /**
     * The base-material rows this consolidates: leaf nodes are their own base material;
     * an Assembly/WIP row is never itself consolidated (docs §10.5).
     */
    public function isConsolidationCandidate(): bool
    {
        return ! $this->is_wip;
    }
}
