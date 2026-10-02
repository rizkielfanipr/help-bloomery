<?php

namespace App\Models;

use App\Enums\StoreSalesOrderEventType;
use App\Enums\StoreSalesOrderStatus;
use Database\Factories\StoreSalesOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** docs/store-sales-order-prd.md §15.1. */
class StoreSalesOrder extends Model
{
    /** @use HasFactory<StoreSalesOrderFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * `product_sales_number_if_active` is a generated column that exists purely to make the
     * (company_code_snapshot, esb_branch_id_snapshot, product_sales_number) uniqueness
     * soft-delete-safe on MySQL/SQLite. It has no application meaning and is never read/written
     * by name.
     */
    protected $hidden = ['product_sales_number_if_active'];

    protected $fillable = [
        'branch_id',
        'branch_esb_code_id',
        'company_code_snapshot',
        'branch_code_snapshot',
        'esb_branch_id_snapshot',
        'branch_name_snapshot',
        'product_sales_number',
        'product_sales_date',
        'required_date',
        'customer_id_snapshot',
        'customer_name_snapshot',
        'customer_address_snapshot',
        'product_sales_total',
        'currency_sign',
        'esb_status_id',
        'esb_status_name',
        'esb_created_by',
        'link_purchase_number',
        'esb_additional_info',
        'esb_snapshot',
        'last_verified_at',
        'phone_number',
        'ordered_by',
        'event_type',
        'event_type_other',
        'delivery_time',
        'preparation_notes',
        'attachment_paths',
        'operational_status',
        'cancellation_reason',
        'submitted_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'product_sales_date' => 'datetime',
            'required_date' => 'datetime',
            'product_sales_total' => 'decimal:2',
            'esb_snapshot' => 'array',
            'last_verified_at' => 'datetime',
            'attachment_paths' => 'array',
            'event_type' => StoreSalesOrderEventType::class,
            'operational_status' => StoreSalesOrderStatus::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function branchEsbCode(): BelongsTo
    {
        return $this->belongsTo(BranchEsbCode::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreSalesOrderItem::class)->orderBy('sort_order');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(StoreSalesOrderActivity::class)->latest('id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
