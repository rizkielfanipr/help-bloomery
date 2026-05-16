<?php

namespace App\Models;

use Database\Factories\PurchasingRequestItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasingRequestItem extends Model
{
    /** @use HasFactory<PurchasingRequestItemFactory> */
    use HasFactory;

    protected $fillable = [
        'purchasing_request_id',
        'nama_barang',
        'jumlah',
        'satuan',
        'spesifikasi',
        'estimated_price',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'estimated_price' => 'decimal:2',
        ];
    }

    public function purchasingRequest(): BelongsTo
    {
        return $this->belongsTo(PurchasingRequest::class);
    }
}
