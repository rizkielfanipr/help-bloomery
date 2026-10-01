<?php

namespace App\Models;

use Database\Factories\CustomerComplaintActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/customer-complaints-prd.md §14.2. Append-only through normal use cases: every write goes
 * through an Action alongside the CustomerComplaint change it documents, in the same transaction.
 */
class CustomerComplaintActivity extends Model
{
    /** @use HasFactory<CustomerComplaintActivityFactory> */
    use HasFactory;

    public const TYPE_CREATED = 'created';

    public const TYPE_STATUS_CHANGED = 'status_changed';

    public const TYPE_PIC_CHANGED = 'pic_changed';

    public const TYPE_NOTES_UPDATED = 'notes_updated';

    public const TYPE_RESOLUTION_UPDATED = 'resolution_updated';

    protected $fillable = [
        'customer_complaint_id',
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

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(CustomerComplaint::class, 'customer_complaint_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
