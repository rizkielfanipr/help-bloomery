<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCardApproval extends Model
{
    protected $fillable = [
        'stock_card_id',
        'stage',
        'action',
        'actor_id',
        'notes',
        'revision_number',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
        ];
    }

    public function stockCard(): BelongsTo
    {
        return $this->belongsTo(StockCard::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
