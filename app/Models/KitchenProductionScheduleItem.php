<?php

namespace App\Models;

use Database\Factories\KitchenProductionScheduleItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One BOM Assembly on a production schedule (docs/products/kitchen/production-schedule/prd.md §11.2).
 * `planned_quantity` is output in the BOM's result UOM and is handled as a decimal string, never a
 * float. Its lightweight identity snapshot is refreshed from the local catalog on every save.
 */
class KitchenProductionScheduleItem extends Model
{
    /** @use HasFactory<KitchenProductionScheduleItemFactory> */
    use HasFactory;

    protected $fillable = [
        'kitchen_production_schedule_id',
        'rnd_bom_catalog_id',
        'esb_bom_id',
        'bom_code',
        'bom_name',
        'bom_type_id',
        'product_detail_id',
        'product_code',
        'product_name',
        'uom_name',
        'planned_quantity',
        'sequence',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'esb_bom_id' => 'integer',
            'bom_type_id' => 'integer',
            'product_detail_id' => 'integer',
            'planned_quantity' => 'decimal:4',
            'sequence' => 'integer',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(KitchenProductionSchedule::class, 'kitchen_production_schedule_id');
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(RndBomCatalog::class, 'rnd_bom_catalog_id');
    }
}
