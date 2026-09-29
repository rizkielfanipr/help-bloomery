<?php

namespace App\Models;

use App\Enums\RndBomCatalogSyncStatus;
use Database\Factories\RndBomCatalogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Local read model / snapshot of one ESB Bill of Material Assembly, used by the BOM Adjustment
 * index so pagination and search stay database-driven instead of re-fetching the whole ESB
 * catalog on every render (docs/rnd-bom-adjustment-prd.md §8).
 */
class RndBomCatalog extends Model
{
    /** @use HasFactory<RndBomCatalogFactory> */
    use HasFactory;

    protected $fillable = [
        'esb_bom_id',
        'bom_code',
        'bom_name',
        'bom_type_id',
        'bom_type_name',
        'product_detail_id',
        'product_code',
        'product_name',
        'uom_name',
        'component_count',
        'is_active',
        'detail_snapshot',
        'esb_edited_at',
        'sync_status',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'esb_bom_id' => 'integer',
            'bom_type_id' => 'integer',
            'product_detail_id' => 'integer',
            'component_count' => 'integer',
            'is_active' => 'boolean',
            'detail_snapshot' => 'array',
            'sync_status' => RndBomCatalogSyncStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Search by BOM code/name or result product code/name (docs/rnd-bom-adjustment-prd.md §10).
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('bom_code', 'like', "%{$term}%")
                ->orWhere('bom_name', 'like', "%{$term}%")
                ->orWhere('product_code', 'like', "%{$term}%")
                ->orWhere('product_name', 'like', "%{$term}%");
        });
    }
}
