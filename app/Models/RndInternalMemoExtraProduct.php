<?php

namespace App\Models;

use Database\Factories\RndInternalMemoExtraProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product added by hand to a Memo's Product Active summary: `scope` store|kitchen and `kind`
 * wip|raw place it in WIP Store, RAW Store, WIP Kitchen, or RAW Kitchen. It carries the same
 * Purchase UOM / Minimum Order fields as a BOM material so the summary treats both alike.
 */
class RndInternalMemoExtraProduct extends Model
{
    /** @use HasFactory<RndInternalMemoExtraProductFactory> */
    use HasFactory;

    public const KIND_WIP = 'wip';

    public const KIND_RAW = 'raw';

    protected $fillable = [
        'rnd_internal_memo_id',
        'scope',
        'kind',
        'esb_product_id',
        'esb_product_detail_id',
        'product_code',
        'product_name',
        'uom_name',
        'category_name',
        'purchase_uom_id',
        'purchase_uom_name',
        'minimum_order',
        'product_detail_snapshot',
        'product_synced_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'esb_product_id' => 'integer',
            'esb_product_detail_id' => 'integer',
            'purchase_uom_id' => 'integer',
            'minimum_order' => 'decimal:4',
            'product_detail_snapshot' => 'array',
            'product_synced_at' => 'datetime',
        ];
    }

    public function memo(): BelongsTo
    {
        return $this->belongsTo(RndInternalMemo::class, 'rnd_internal_memo_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isWip(): bool
    {
        return $this->kind === self::KIND_WIP;
    }

    public function hasPurchaseUom(): bool
    {
        return filled($this->purchase_uom_name);
    }
}
