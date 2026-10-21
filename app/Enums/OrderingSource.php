<?php

namespace App\Enums;

/**
 * ESB sources snapshotted for Ordering Recommendation. Sales (menu) data is not snapshotted in
 * this version: the sales payload carries no stable menu identity yet, and Daily Usage is already
 * the product demand (docs/ordering-recommendation-prd.md §9.1, Phase 0).
 */
enum OrderingSource: string
{
    case DailyUsage = 'daily_usage';
    case StockMovement = 'stock_movement';

    public function label(): string
    {
        return match ($this) {
            self::DailyUsage => 'Daily Usage',
            self::StockMovement => 'Stock Movement',
        };
    }
}
