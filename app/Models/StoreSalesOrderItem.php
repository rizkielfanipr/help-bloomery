<?php

namespace App\Models;

use App\Enums\StoreSalesOrderProductType;
use Database\Factories\StoreSalesOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** docs/store-sales-order-prd.md §15.2 — a local operational note, not an official ESB line item. */
class StoreSalesOrderItem extends Model
{
    /** @use HasFactory<StoreSalesOrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'store_sales_order_id',
        'product_type',
        'custom_detail',
        'quantity',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'product_type' => StoreSalesOrderProductType::class,
            'quantity' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreSalesOrder::class, 'store_sales_order_id');
    }
}
