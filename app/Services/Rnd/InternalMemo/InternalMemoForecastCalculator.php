<?php

namespace App\Services\Rnd\InternalMemo;

use App\Models\RndInternalMemoMenu;
use Illuminate\Support\Facades\DB;

/**
 * Every material row's `quantity_per_menu` is already the centrally calculated, fully propagated
 * per-one-unit-of-Menu quantity. This final step multiplies it by the Menu Forecast Quantity.
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
