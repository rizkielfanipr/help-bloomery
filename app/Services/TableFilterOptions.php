<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ErpModule;
use App\Models\ItRequestType;

class TableFilterOptions
{
    /** @var array<int, string>|null */
    private ?array $branches = null;

    /** @var array<int, string>|null */
    private ?array $erpModules = null;

    /** @var array<int, string>|null */
    private ?array $itRequestTypes = null;

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
}
