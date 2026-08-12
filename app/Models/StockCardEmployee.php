<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCardEmployee extends Model
{
    protected $fillable = [
        'stock_card_id',
        'employee_id',
        'employee_code',
        'employee_name',
        'employee_position',
    ];

    public function stockCard(): BelongsTo
    {
        return $this->belongsTo(StockCard::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
