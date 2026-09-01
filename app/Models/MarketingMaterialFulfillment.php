<?php

namespace App\Models;

use App\Enums\MarketingMaterialFulfillmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingMaterialFulfillment extends Model
{
    protected $fillable = [
        'rnd_project_marketing_material_id',
        'status',
        'vendor_name',
        'order_date',
        'estimated_completion_date',
        'purchasing_notes',
        'ordered_by',
        'ordered_at',
        'received_quantity',
        'received_date',
        'location_id',
        'inventory_notes',
        'received_by',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MarketingMaterialFulfillmentStatus::class,
            'order_date' => 'date',
            'estimated_completion_date' => 'date',
            'ordered_at' => 'datetime',
            'received_quantity' => 'integer',
            'received_date' => 'date',
            'received_at' => 'datetime',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(RndProjectMarketingMaterial::class, 'rnd_project_marketing_material_id');
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
