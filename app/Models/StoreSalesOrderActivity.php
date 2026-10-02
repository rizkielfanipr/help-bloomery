<?php

namespace App\Models;

use Database\Factories\StoreSalesOrderActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/store-sales-order-prd.md §15.3. Append-only through normal use cases: every write goes
 * through an Action alongside the StoreSalesOrder change it documents, in the same transaction.
 */
class StoreSalesOrderActivity extends Model
{
    /** @use HasFactory<StoreSalesOrderActivityFactory> */
    use HasFactory;

    public const TYPE_CREATED = 'created';

    public const TYPE_STATUS_CHANGED = 'status_changed';

    public const TYPE_INFO_UPDATED = 'info_updated';

    public const TYPE_SNAPSHOT_REFRESHED = 'snapshot_refreshed';

    protected $fillable = [
        'store_sales_order_id',
        'activity_type',
        'previous_status',
        'new_status',
        'notes',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreSalesOrder::class, 'store_sales_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
