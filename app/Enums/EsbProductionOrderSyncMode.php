<?php

namespace App\Enums;

/**
 * docs/products/kitchen/production-schedule/prd.md §30.6: the scheduler only runs `Incremental`;
 * `Backfill` is started explicitly from the backfill command.
 */
enum EsbProductionOrderSyncMode: string
{
    case Incremental = 'incremental';
    case Backfill = 'backfill';
}
