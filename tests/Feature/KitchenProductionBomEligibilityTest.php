<?php

use App\Enums\RndBomCatalogSyncStatus;
use App\Models\RndBomCatalog;
use App\Services\Kitchen\ProductionBomEligibilityService;

beforeEach(function () {
    $this->eligibility = app(ProductionBomEligibilityService::class);
});

function schedulableAssembly(array $overrides = []): RndBomCatalog
{
    return RndBomCatalog::factory()->create($overrides);
}

it('accepts an active synced Assembly without a freshness setting', function () {
    $catalog = schedulableAssembly(['last_synced_at' => '2020-01-01 00:00:00']);

    expect($this->eligibility->reasonFor($catalog))->toBeNull()
        ->and($this->eligibility->eligibleQuery()->pluck('id')->all())->toBe([$catalog->id])
        ->and(config('kitchen.production_schedule.bom_catalog_max_age_hours'))->toBeNull();
});

it('rejects rows that are not eligible in the R&D Assembly catalog', function (array $overrides, string $reason) {
    $catalog = schedulableAssembly($overrides);

    expect($this->eligibility->reasonFor($catalog))->toBe($reason)
        ->and($this->eligibility->eligibleQuery()->whereKey($catalog->id)->exists())->toBeFalse();
})->with([
    'Menu type' => [['bom_type_id' => 3, 'bom_type_name' => 'Menu'], 'Bukan BOM Assembly.'],
    'spoofed Assembly name' => [['bom_type_id' => 3, 'bom_type_name' => 'Assembly'], 'Bukan BOM Assembly.'],
    'inactive' => [['is_active' => false], 'BOM tidak aktif di ESB.'],
    'failed sync' => [['sync_status' => RndBomCatalogSyncStatus::Failed], 'BOM belum tersinkron dengan benar (Gagal).'],
    'missing result' => [['product_detail_id' => null], 'Produk hasil BOM tidak lengkap.'],
    'missing UOM' => [['uom_name' => null], 'Satuan hasil BOM tidak tersedia.'],
]);

it('rejects a forged catalog ID', function () {
    expect($this->eligibility->reasonFor(RndBomCatalog::find(987654)))->toBe('BOM tidak ditemukan di katalog.');
});
