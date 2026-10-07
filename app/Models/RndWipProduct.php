<?php

namespace App\Models;

use App\Enums\RndWipShelfLifeStatus;
use Database\Factories\RndWipProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Local snapshot of one active ESB Product Detail (unit) in category "Barang WIP". The Shelf Life
 * menu lists one row per Product — its base unit — and masters are keyed by that base Product
 * Detail ID; non-base rows let WipShelfLifeResolver map other units back to it. Refreshed by SyncWipProductCatalogAction; rows that disappear from ESB are only
 * flagged inactive so their Shelf Life master keeps a readable identity.
 */
class RndWipProduct extends Model
{
    /** @use HasFactory<RndWipProductFactory> */
    use HasFactory;

    protected $fillable = [
        'company_code',
        'esb_product_id',
        'product_detail_id',
        'product_code',
        'product_name',
        'uom_name',
        'is_base',
        'category_name',
        'is_active',
        'last_synced_at',
    ];

    protected $attributes = [
        'company_code' => RndProductEsbShelfLife::DEFAULT_COMPANY_CODE,
        'is_active' => true,
        'is_base' => true,
    ];

    protected function casts(): array
    {
        return [
            'esb_product_id' => 'integer',
            'product_detail_id' => 'integer',
            'is_active' => 'boolean',
            'is_base' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * The local WIP Shelf Life master of this Product Detail. Not a foreign key: the master must
     * survive catalog re-syncs.
     */
    public function wipShelfLife(): HasOne
    {
        return $this->hasOne(RndProductEsbShelfLife::class, 'esb_product_detail_id', 'product_detail_id')
            ->where('company_code', RndProductEsbShelfLife::DEFAULT_COMPANY_CODE);
    }

    /**
     * @param  Builder<RndWipProduct>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * One row per active Product: its base unit.
     *
     * @param  Builder<RndWipProduct>  $query
     */
    public function scopeListedProducts(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_base', true);
    }

    /**
     * Shelf Life data-status filter, applied in SQL so pagination stays correct.
     *
     * @param  Builder<RndWipProduct>  $query
     */
    public function scopeWithShelfLifeStatus(Builder $query, ?RndWipShelfLifeStatus $status): Builder
    {
        return match ($status) {
            null, RndWipShelfLifeStatus::IdentityIncomplete => $query,
            RndWipShelfLifeStatus::Complete => $query->whereHas('wipShelfLife', fn (Builder $query) => $query->where('is_active', true)),
            RndWipShelfLifeStatus::Inactive => $query->whereHas('wipShelfLife', fn (Builder $query) => $query->where('is_active', false)),
            RndWipShelfLifeStatus::Missing => $query->whereDoesntHave('wipShelfLife'),
        };
    }

    /**
     * @param  Builder<RndWipProduct>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('product_code', 'like', "%{$term}%")
                ->orWhere('product_name', 'like', "%{$term}%");
        });
    }
}
