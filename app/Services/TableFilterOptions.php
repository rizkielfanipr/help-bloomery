<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ErpModule;
use App\Models\ItRequestType;
use App\Models\StoreSalesOrder;

class TableFilterOptions
{
    /** @var array<int, string>|null */
    private ?array $branches = null;

    /** @var array<int, string>|null */
    private ?array $erpModules = null;

    /** @var array<int, string>|null */
    private ?array $itRequestTypes = null;

    /** @var array<string, string>|null */
    private ?array $storeSalesOrderEsbStatuses = null;

    /** @return array<int, string> */
    public function branches(): array
    {
        return $this->branches ??= Branch::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    public function erpModules(): array
    {
        return $this->erpModules ??= ErpModule::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    public function itRequestTypes(): array
    {
        return $this->itRequestTypes ??= ItRequestType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<string, string> */
    public function storeSalesOrderEsbStatuses(): array
    {
        return $this->storeSalesOrderEsbStatuses ??= StoreSalesOrder::query()
            ->whereNotNull('esb_status_name')
            ->distinct()
            ->orderBy('esb_status_name')
            ->pluck('esb_status_name', 'esb_status_name')
            ->all();
    }
}
