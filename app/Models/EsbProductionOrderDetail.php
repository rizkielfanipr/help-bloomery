<?php

namespace App\Models;

use Database\Factories\EsbProductionOrderDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One BOM/product row of an ESB Production Order snapshot (docs/products/kitchen/production-schedule/prd.md §29.2).
 * `materials` is a JSON list of {product_code, product_name, quantity, uom_name} with decimal strings.
 */
class EsbProductionOrderDetail extends Model
{
    /** @use HasFactory<EsbProductionOrderDetailFactory> */
    use HasFactory;

    protected $fillable = [
        'esb_production_order_id', 'external_detail_id', 'bom_id', 'bom_name', 'bom_type_name', 'product_detail_id',
        'product_code', 'product_name', 'quantity', 'uom_name', 'notes', 'is_finished', 'materials', 'sequence',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'is_finished' => 'boolean',
            'materials' => 'array',
            'sequence' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EsbProductionOrder::class, 'esb_production_order_id');
    }
}
