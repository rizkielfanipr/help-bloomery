<?php

use App\Actions\Rnd\InternalMemo\UpdateInternalMemoPurchasingUomAction;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ViewRndInternalMemo;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMaterial;
use App\Models\User;
use App\Services\Rnd\InternalMemo\InternalMemoConsolidationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->operator = User::factory()->create(['is_active' => true]);
    $this->operator->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo', 'update rnd internal memo']);
    $this->actingAs($this->operator);
    $this->memo = RndInternalMemo::factory()->create();
    $this->menu = $this->memo->menus()->create([
        'company_code' => 'BLSS', 'esb_menu_id' => 501, 'menu_name' => 'Menu Uji',
        'esb_bom_id' => 42, 'release_date' => now(), 'menu_snapshot' => [],
    ]);
    $this->snapshot = ['productDetails' => [
        ['productDetailID' => 101, 'uomID' => 5, 'uomName' => 'GR', 'flagActive' => true],
        ['productDetailID' => 102, 'uomID' => 31, 'uomName' => 'PACK@525GR', 'flagActive' => true],
        ['productDetailID' => 103, 'uomID' => 9, 'uomName' => 'PACK LAMA', 'flagActive' => false],
    ]];
    $this->material = fn (array $attributes = []): RndInternalMemoMaterial => $this->menu->materials()->create([
        'source_bom_id' => 42, 'source_path' => [], 'depth' => 0,
        'esb_product_id' => 10, 'esb_product_detail_id' => 101,
        'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR',
        'quantity_per_menu' => 250, 'net_quantity' => 250, 'is_wip' => false, 'is_packaging' => false,
        'product_detail_snapshot' => $this->snapshot, 'product_synced_at' => now(), ...$attributes,
    ]);
});

it('shows active registered UOMs and saves one choice for every consolidated row', function () {
    $first = ($this->material)(['purchase_uom_id' => 31, 'purchase_uom_name' => 'PACK@525GR']);
    $second = ($this->material)(['source_bom_id' => 43, 'net_quantity' => 100]);
    $key = app(InternalMemoConsolidationService::class)->consolidateForSummary($this->memo)['store']['bahan'][0]['key'];

    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])
        ->assertSee('Purchasing UOM')->assertSee('PACK@525GR')->assertDontSee('PACK LAMA')
        ->call('savePurchasingUom', $key, '5')->assertHasNoErrors()
        ->assertNotified('UOM Purchasing tersimpan');

    expect($first->fresh()->only(['purchase_uom_id', 'purchase_uom_name', 'purchasing_uom_id', 'purchasing_uom_name']))
        ->toBe(['purchase_uom_id' => 31, 'purchase_uom_name' => 'PACK@525GR', 'purchasing_uom_id' => 5, 'purchasing_uom_name' => 'GR'])
        ->and($second->fresh()->purchasing_uom_id)->toBe(5)
        ->and((float) $first->fresh()->net_quantity)->toBe(250.0)
        ->and($first->fresh()->uom_name)->toBe('GR');
});

it('rejects an unknown or inactive UOM', function () {
    ($this->material)();
    $key = app(InternalMemoConsolidationService::class)->consolidateForSummary($this->memo)['store']['bahan'][0]['key'];

    expect(fn () => app(UpdateInternalMemoPurchasingUomAction::class)->execute($this->memo, $key, 999, $this->operator))
        ->toThrow(ValidationException::class, 'tidak terdaftar')
        ->and(fn () => app(UpdateInternalMemoPurchasingUomAction::class)->execute($this->memo, $key, 9, $this->operator))
        ->toThrow(ValidationException::class, 'tidak terdaftar');
});

it('requires update permission and supports legacy snapshots', function () {
    $material = ($this->material)(['product_detail_snapshot' => [
        'matchedProductDetail' => ['productDetailID' => 101, 'uomID' => 5, 'uomName' => 'GR', 'flagActive' => true],
        'purchaseProductDetail' => ['productDetailID' => 102, 'uomID' => 31, 'uomName' => 'PACK@525GR', 'flagActive' => true],
    ]]);
    $key = app(InternalMemoConsolidationService::class)->consolidateForSummary($this->memo)['store']['bahan'][0]['key'];
    $viewer = User::factory()->create(['is_active' => true]);

    expect(fn () => app(UpdateInternalMemoPurchasingUomAction::class)->execute($this->memo, $key, 31, $viewer))
        ->toThrow(AuthorizationException::class);

    app(UpdateInternalMemoPurchasingUomAction::class)->execute($this->memo, $key, 31, $this->operator);
    expect($material->fresh()->purchasing_uom_name)->toBe('PACK@525GR');
});
