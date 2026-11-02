<?php

namespace App\Models;

use Database\Factories\NonPoReceiptBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NonPoReceiptBatch extends Model
{
    /** @use HasFactory<NonPoReceiptBatchFactory> */
    use HasFactory;

    protected $fillable = ['non_po_receipt_item_id', 'batch_number', 'manufactured_date', 'expired_date', 'quantity', 'accepted_qty', 'hold_qty', 'rejected_qty'];

    protected function casts(): array
    {
        return [
            'manufactured_date' => 'date', 'expired_date' => 'date', 'quantity' => 'decimal:4',
            'accepted_qty' => 'decimal:4', 'hold_qty' => 'decimal:4', 'rejected_qty' => 'decimal:4',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(NonPoReceiptItem::class, 'non_po_receipt_item_id');
    }
}
