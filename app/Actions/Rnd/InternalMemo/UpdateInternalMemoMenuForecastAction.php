<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemoMenu;
use App\Services\Rnd\InternalMemo\InternalMemoForecastCalculator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * docs/rnd-internal-memo-prd.md §7.3, §10.1. Forecast Quantity is local data, kept separate from
 * the BOM sync so editing it never triggers a new ESB call.
 */
class UpdateInternalMemoMenuForecastAction
{
    /** Mirrors RndInternalMemoPolicy::updateForecast()'s status gate. */
    private const OPEN_STATUSES = [RndInternalMemoStatus::Draft, RndInternalMemoStatus::NeedsAttention, RndInternalMemoStatus::Ready];

    public function __construct(
        private InternalMemoForecastCalculator $calculator,
        private RecalculateInternalMemoStatusAction $recalculateStatus,
    ) {}

    public function execute(RndInternalMemoMenu $menu, float $forecastQuantity): RndInternalMemoMenu
    {
        if (! in_array($menu->memo->status, self::OPEN_STATUSES, true)) {
            throw new RuntimeException('Forecast Quantity hanya dapat diubah selama Memo belum Finalized.');
        }

        if ($forecastQuantity < 0) {
            throw ValidationException::withMessages(['forecast_quantity' => 'Forecast Quantity tidak boleh negatif.']);
        }

        $menu->update(['forecast_quantity' => $forecastQuantity]);
        $this->calculator->recalculateMenu($menu);
        $this->recalculateStatus->execute($menu->memo);

        return $menu->refresh();
    }
}
