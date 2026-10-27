<?php

namespace App\Models;

use App\Enums\CustomerComplaintCategory;
use App\Enums\CustomerComplaintSource;
use Database\Factories\CustomerComplaintFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** docs/products/operational/customer-complaints/prd.md §14.1. */
class CustomerComplaint extends Model
{
    /** @use HasFactory<CustomerComplaintFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'complaint_number',
        'branch_id',
        'occurred_at',
        'source',
        'category',
        'order_reference',
        'customer_name',
        'customer_contact',
        'description',
        'attachment_paths',
        'submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'source' => CustomerComplaintSource::class,
            'category' => CustomerComplaintCategory::class,
            'attachment_paths' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
