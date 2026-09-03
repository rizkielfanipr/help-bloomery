<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RndProductEsbMaterialUnit extends Model
{
    protected $fillable = [
        'rnd_product_esb_material_id',
        'uom_id',
        'uom_name',
        'esb_product_detail_id',
        'sku',
        'conversion_factor',
        'base_price',
        'is_base',
        'is_stock',
        'is_purchase',
        'is_transfer',
        'is_sales',
        'flag_active',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:4',
            'base_price' => 'decimal:4',
            'esb_product_detail_id' => 'integer',
            'is_base' => 'boolean',
            'is_stock' => 'boolean',
            'is_purchase' => 'boolean',
            'is_transfer' => 'boolean',
            'is_sales' => 'boolean',
            'flag_active' => 'boolean',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(RndProductEsbMaterial::class, 'rnd_product_esb_material_id');
    }
}
