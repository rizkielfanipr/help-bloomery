<?php

namespace App\Actions\Kitchen;

use App\Models\KitchenProductionSchedule;
use App\Models\RndBomCatalog;

/**
 * Writes the schedule items in the given order with contiguous sequences. Display identity comes
 * from the re-queried catalog row, never from the browser.
 */
final class ProductionScheduleItemWriter
{
    /**
     * @param  list<array{catalog: RndBomCatalog, planned_quantity: string, notes: ?string}>  $items
     */
    public static function write(KitchenProductionSchedule $schedule, array $items): void
    {
        foreach ($items as $index => $item) {
            $catalog = $item['catalog'];

            $schedule->items()->create([
                'rnd_bom_catalog_id' => $catalog->id,
                'esb_bom_id' => $catalog->esb_bom_id,
                'bom_code' => $catalog->bom_code,
                'bom_name' => $catalog->bom_name,
                'bom_type_id' => $catalog->bom_type_id,
                'product_detail_id' => $catalog->product_detail_id,
                'product_code' => $catalog->product_code,
                'product_name' => $catalog->product_name,
                'uom_name' => $catalog->uom_name,
                'planned_quantity' => $item['planned_quantity'],
                'sequence' => $index + 1,
                'notes' => $item['notes'],
            ]);
        }
    }
}
