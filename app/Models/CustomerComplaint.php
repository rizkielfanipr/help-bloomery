<?php

namespace App\Models;

use App\Enums\CustomerComplaintCategory;
use App\Enums\CustomerComplaintSource;
use App\Enums\CustomerComplaintStatus;
use Database\Factories\CustomerComplaintFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** docs/customer-complaints-prd.md §14.1. */
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
        'status',
        'assigned_to',
        'resolution',
        'resolved_at',
        'resolved_by',
        'closed_at',
        'closed_by',
        'submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'source' => CustomerComplaintSource::class,
            'category' => CustomerComplaintCategory::class,
            'attachment_paths' => 'array',
            'status' => CustomerComplaintStatus::class,
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CustomerComplaintActivity::class)->latest('id');
    }
}
