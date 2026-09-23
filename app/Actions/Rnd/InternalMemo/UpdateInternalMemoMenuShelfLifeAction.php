<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemoMenu;
use RuntimeException;

/**
 * docs/rnd-internal-memo-prd.md §7.3, §11. Shelf Life edited here is local to this Memo/Menu; it
 * never writes back to the `rnd_esb_product_shelf_lives` master.
 */
class UpdateInternalMemoMenuShelfLifeAction
{
    /** Mirrors RndInternalMemoPolicy::updateForecast()'s status gate. */
    private const OPEN_STATUSES = [RndInternalMemoStatus::Draft, RndInternalMemoStatus::NeedsAttention, RndInternalMemoStatus::Ready];

    public function __construct(private RecalculateInternalMemoStatusAction $recalculateStatus) {}

    /** @param array{shelf_life_value: ?float, shelf_life_unit: ?string, storage_condition: ?string, shelf_life_notes: ?string} $data */
    public function execute(RndInternalMemoMenu $menu, array $data): RndInternalMemoMenu
    {
        if (! in_array($menu->memo->status, self::OPEN_STATUSES, true)) {
            throw new RuntimeException('Shelf Life hanya dapat diubah selama Memo belum Finalized.');
        }

        $menu->update([
            'shelf_life_value' => $data['shelf_life_value'],
            'shelf_life_unit' => $data['shelf_life_unit'],
            'storage_condition' => $data['storage_condition'],
            'shelf_life_notes' => $data['shelf_life_notes'] ?? null,
        ]);
        $this->recalculateStatus->execute($menu->memo);

        return $menu->refresh();
    }
}
