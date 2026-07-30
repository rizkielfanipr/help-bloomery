<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RndProductEsbMaterial extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'syncing' => 'Syncing',
        'synced' => 'Synced',
        'failed' => 'Failed',
    ];

    protected $fillable = [
        'rnd_project_product_id',
        'category_id',
        'category_name',
        'sub_category_id',
        'sub_category_name',
        'uom_id',
        'uom_name',
        'product_code',
        'product_name',
        'sku',
        'conversion_factor',
        'base_price',
        'notes',
        'status',
        'esb_product_id',
        'esb_is_temp',
        'last_payload',
        'last_response',
        'sync_error',
        'synced_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:4',
            'base_price' => 'decimal:4',
            'esb_is_temp' => 'boolean',
            'last_payload' => 'array',
            'last_response' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RndProjectProduct::class, 'rnd_project_product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function units(): HasMany
    {
        return $this->hasMany(RndProductEsbMaterialUnit::class)
            ->orderByDesc('is_base')
            ->orderBy('id');
    }

    public function toEsbPayload(): array
    {
        return [
            'categoryID' => $this->category_id,
            'subCategoryID' => $this->sub_category_id,
            'productCode' => $this->product_code,
            'productName' => $this->product_name,
            'requestable' => true,
            'purchasable' => true,
            'saleable' => false,
            'receiptTolerance' => 0,
            'notes' => $this->notes,
            'vat' => false,
            'bomID' => null,
            'flagLuxuryItem' => 0,
            'coretaxProductCodeID' => null,
            'pushToGoApp' => false,
            'productDetails' => $this->payloadUnits(),
            'customFields' => [
                'field1' => null,
                'field2' => null,
                'field3' => null,
                'field4' => null,
                'field5' => null,
            ],
        ];
    }

    private function payloadUnits(): array
    {
        $units = $this->units()->get();

        if ($units->isEmpty()) {
            return [[
                'productDetailID' => null,
                'uomID' => $this->uom_id,
                'basePrice' => (float) $this->base_price,
                'sku' => $this->sku,
                'cubication' => 0,
                'weight' => 0,
                'qty' => 1,
                'isStock' => true,
                'isPurchase' => true,
                'isTransfer' => true,
                'isSales' => false,
                'isBase' => true,
                'menuID' => null,
                'flagActive' => true,
            ]];
        }

        return $units->map(fn (RndProductEsbMaterialUnit $unit): array => [
            'productDetailID' => null,
            'uomID' => $unit->uom_id,
            'basePrice' => (float) $unit->base_price,
            'sku' => $unit->sku,
            'cubication' => 0,
            'weight' => 0,
            'qty' => (float) $unit->conversion_factor,
            'isStock' => $unit->is_stock,
            'isPurchase' => $unit->is_purchase,
            'isTransfer' => $unit->is_transfer,
            'isSales' => $unit->is_sales,
            'isBase' => $unit->is_base,
            'menuID' => null,
            'flagActive' => $unit->flag_active,
        ])->values()->all();
    }
}
