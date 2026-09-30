<?php

use App\Models\RndInternalMemoMaterial;
use App\Models\RndInternalMemoMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('has no Purchase UOM by default, matching an unproven ESB contract rather than an unfetched one', function () {
    $material = RndInternalMemoMaterial::factory()->create();

    expect($material->hasPurchaseUom())->toBeFalse()
        ->and($material->purchase_uom_id)->toBeNull()
        ->and($material->purchase_uom_name)->toBeNull()
        ->and($material->minimum_order)->toBeNull()
        ->and($material->product_synced_at)->toBeNull();
});

it('records a proven Purchase UOM via the withPurchaseUom factory state', function () {
    $material = RndInternalMemoMaterial::factory()->withPurchaseUom('KG', 2)->create();

    expect($material->hasPurchaseUom())->toBeTrue()
        ->and($material->purchase_uom_id)->toBe(2)
        ->and($material->purchase_uom_name)->toBe('KG')
        ->and($material->product_synced_at)->not->toBeNull();
});

it('stores a local Minimum Order independent of the ESB snapshot', function () {
    $material = RndInternalMemoMaterial::factory()->withMinimumOrder(25.5)->create();

    expect((float) $material->minimum_order)->toBe(25.5);
});

it('keeps Minimum Order as a decimal cast distinct from quantity_per_menu and net_quantity', function () {
    $menu = RndInternalMemoMenu::factory()->create();
    $material = $menu->materials()->create([
        'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR',
        'quantity_per_menu' => 250, 'net_quantity' => 500, 'minimum_order' => 1000,
        'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false,
    ]);

    expect((float) $material->fresh()->minimum_order)->toBe(1000.0);
});
