<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RndProductRegionalPrice extends Model
{
    protected $fillable = [
        'rnd_project_product_id',
        'sales_region_id',
        'offline_price',
        'online_price',
        'effective_from',
        'effective_to',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'offline_price' => 'decimal:2',
            'online_price' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RndProjectProduct::class, 'rnd_project_product_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(SalesRegion::class, 'sales_region_id');
    }
}
