<?php

namespace App\Services\Rnd\InternalMemo;

use App\Models\RndInternalMemoMenu;
use Illuminate\Support\Facades\DB;

/**
 * docs/rnd-internal-memo-prd.md §10.1-10.2, simplified on explicit user instruction: no output
 * yield division and no waste/tolerance/gross calculation. Every material row's
 * `quantity_per_menu` is already the fully propagated per-one-unit-of-Menu quantity (computed by
 * InternalMemoBomResolver), so the only remaining step is multiplying it by the Menu's own
 * Forecast Quantity.
 */
class InternalMemoForecastCalculator
{
    public function recalculateMenu(RndInternalMemoMenu $menu): void
    {
        $forecastQuantity = number_format((float) $menu->forecast_quantity, 6, '.', '');

        // A single bulk UPDATE instead of one query per row; $forecastQuantity is a formatted
        // float (never user-controlled text), so it is safe to interpolate into the expression.
        $menu->materials()->update([
            'net_quantity' => DB::raw("quantity_per_menu * {$forecastQuantity}"),
        ]);
    }
}
