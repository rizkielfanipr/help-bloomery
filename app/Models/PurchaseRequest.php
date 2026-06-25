<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use App\Enums\PurchaseType;
use Database\Factories\PurchaseRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequest extends Model
{
    /** @use HasFactory<PurchaseRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'division',
        'item_name',
        'quantity',
        'purchase_reason',
        'purchase_type',
        'journal_item_number',
        'purchase_request_number',
        'ecommerce_link',
        'attachment_paths',
        'status',
        'admin_notes',
        'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_type' => PurchaseType::class,
            'status' => PurchaseRequestStatus::class,
            'attachment_paths' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
