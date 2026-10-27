<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ErpModule;
use App\Models\ItRequestType;
use App\Models\RndMaterialPricingRequest;
use App\Models\RndMaterialReview;
use App\Models\StoreSalesOrder;
use App\Models\User;

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

    /** @var array<int, string>|null */
    private ?array $materialReviewSubmitters = null;

    /** @var array<string, string>|null */
    private ?array $materialPricelistCategories = null;

    /** @var array<string, string>|null */
    private ?array $materialPricelistSuppliers = null;

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

    /** @return array<int, string> */
    public function materialReviewSubmitters(): array
    {
        return $this->materialReviewSubmitters ??= User::query()
            ->whereIn('id', RndMaterialReview::query()->select('submitted_by'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<string, string> */
    public function materialPricelistCategories(): array
    {
        return $this->materialPricelistCategories ??= RndMaterialPricingRequest::query()
            ->join('rnd_product_esb_materials', 'rnd_product_esb_materials.id', '=', 'rnd_material_pricing_requests.rnd_product_esb_material_id')
            ->whereNotNull('rnd_product_esb_materials.category_name')
            ->distinct()
            ->orderBy('rnd_product_esb_materials.category_name')
            ->pluck('rnd_product_esb_materials.category_name', 'rnd_product_esb_materials.category_name')
            ->all();
    }

    /** @return array<string, string> */
    public function materialPricelistSuppliers(): array
    {
        return $this->materialPricelistSuppliers ??= RndMaterialPricingRequest::query()
            ->whereNotNull('supplier_name')
            ->distinct()
            ->orderBy('supplier_name')
            ->pluck('supplier_name', 'supplier_name')
            ->all();
    }
}
