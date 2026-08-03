<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestStatusHistory extends Model
{
    protected $fillable = [
        'purchase_request_id',
        'from_status',
        'to_status',
        'changed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => PurchaseRequestStatus::class,
            'to_status' => PurchaseRequestStatus::class,
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
