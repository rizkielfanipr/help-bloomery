<?php

namespace App\Models;

use App\Enums\NonPoReceiptStatus;
use Database\Factories\NonPoReceiptStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NonPoReceiptStatusHistory extends Model
{
    /** @use HasFactory<NonPoReceiptStatusHistoryFactory> */
    use HasFactory;

    protected $fillable = ['non_po_receipt_id', 'from_status', 'to_status', 'actor_id', 'notes'];

    protected function casts(): array
    {
        return ['from_status' => NonPoReceiptStatus::class, 'to_status' => NonPoReceiptStatus::class];
    }

    public function nonPoReceipt(): BelongsTo
    {
        return $this->belongsTo(NonPoReceipt::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
