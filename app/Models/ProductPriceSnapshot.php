<?php

namespace App\Models;

use Database\Factories\ProductPriceSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPriceSnapshot extends Model
{
    /** @use HasFactory<ProductPriceSnapshotFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'total_quantity' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'weighted_average_price' => 'decimal:4',
            'synced_at' => 'datetime',
        ];
    }
}
