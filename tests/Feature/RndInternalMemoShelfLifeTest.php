<?php

use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ViewRndInternalMemo;
use App\Models\RndInternalMemo;
use App\Models\RndProductEsbShelfLife;
use App\Models\RndWipProduct;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

/**
 * The Memo product summary shows each WIP's Shelf Life master next to Purchase UOM and Minimum
 * Order, and lets a permitted user fill a missing one from the Memo page (create only).
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->filler = User::factory()->create(['is_active' => true]);
    $this->filler->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo', 'update rnd internal memo', 'manage wip shelf life']);

    $this->memo = RndInternalMemo::factory()->create();
    $this->menu = $this->memo->menus()->create(['company_code' => 'BLSS', 'esb_menu_id' => 501, 'menu_name' => 'Mille Crepe', 'esb_bom_id' => 42, 'release_date' => now(), 'menu_snapshot' => []]);
    $row = fn (array $attributes) => $this->menu->materials()->create([
        'uom_name' => 'GR', 'quantity_per_menu' => 1, 'net_quantity' => 0, 'source_bom_id' => 42, 'source_path' => ['BOM Mille Crepe'],
        'depth' => 0, 'is_wip' => false, 'is_packaging' => false, ...$attributes,
    ]);
    $row(['esb_product_detail_id' => 1, 'product_code' => 'RM-BOX', 'product_name' => 'Box Cake']);
    $wip = $row(['esb_product_detail_id' => 200, 'product_code' => 'BW-CREPE', 'product_name' => 'Crepe Sheet', 'is_wip' => true]);
    $row(['esb_product_detail_id' => 300, 'product_code' => 'BW212', 'product_name' => 'PRX | CRP02', 'depth' => 1, 'parent_material_id' => $wip->id, 'is_wip' => true]);
});

it('shows the WIP Shelf Life master next to Purchase UOM and Minimum Order in WIP tables only', function () {
    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 200, 'shelf_life_value' => 3, 'shelf_life_unit' => 'day', 'storage_condition' => 'chiller']);
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo']);
    $this->actingAs($viewer);

    $html = Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])->html();
    $rawStoreHeader = str($html)->before('summary-store-bahan-')->afterLast('<table')->toString();
    $wipStoreHeader = str($html)->before('summary-store-wip-')->afterLast('<table')->toString();
    expect($rawStoreHeader)->toContain('Purchase UOM')->not->toContain('Shelf Life')
        ->and($wipStoreHeader)->toContain('Shelf Life');

    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])
        ->assertSeeInOrder(['Purchase UOM', 'Shelf Life', 'Minimum Order'])
        ->assertSeeInOrder(['Crepe Sheet', '3 Hari', 'Chiller'])
        ->assertSeeInOrder(['PRX | CRP02', 'Belum diisi'])
        ->assertDontSee('Isi Shelf Life');
});

it('resolves a non-base unit of the WIP to its Product master', function () {
    $base = RndWipProduct::factory()->create(['product_detail_id' => 299, 'esb_product_id' => 9]);
    RndWipProduct::factory()->unitOf($base)->create(['product_detail_id' => 300]);
    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 299, 'shelf_life_value' => 2, 'shelf_life_unit' => 'week']);
    $this->actingAs($this->filler);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])
        ->assertSeeInOrder(['PRX | CRP02', '2 Minggu']);
});

it('fills a missing WIP Shelf Life from the Memo page without any ESB request', function () {
    Http::fake();
    $this->actingAs($this->filler);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])
        ->assertSee('Isi Shelf Life')
        ->call('openMemoShelfLifeModal', 300)
        ->assertSet('shelfLifeModalOpen', true)
        ->assertSee('Product Detail #300')
        ->set('shelfLifeValue', '5')
        ->set('shelfLifeUnit', 'day')
        ->set('shelfLifeStorageCondition', 'frozen')
        ->call('saveMemoShelfLife')
        ->assertHasNoErrors()
        ->assertSet('shelfLifeModalOpen', false)
        ->assertSeeInOrder(['PRX | CRP02', '5 Hari']);

    $master = RndProductEsbShelfLife::query()->sole();
    expect($master->only(['company_code', 'esb_product_detail_id', 'product_code', 'product_name', 'created_by']))
        ->toBe(['company_code' => 'BLSS', 'esb_product_detail_id' => 300, 'product_code' => 'BW212', 'product_name' => 'PRX | CRP02', 'created_by' => $this->filler->id]);
    Http::assertNothingSent();
});

it('keeps the modal and input on validation errors', function () {
    $this->actingAs($this->filler);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])
        ->call('openMemoShelfLifeModal', 200)
        ->set('shelfLifeValue', '0')
        ->set('shelfLifeNotes', 'catatan')
        ->call('saveMemoShelfLife')
        ->assertHasErrors(['shelfLifeValue'])
        ->assertSet('shelfLifeModalOpen', true)
        ->assertSet('shelfLifeNotes', 'catatan');

    expect(RndProductEsbShelfLife::query()->count())->toBe(0);
});

it('never edits an existing master from the Memo and refuses WIPs outside the Memo or without permission', function () {
    $master = RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 200, 'shelf_life_value' => 3, 'shelf_life_unit' => 'day']);
    $this->actingAs($this->filler);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])
        ->call('openMemoShelfLifeModal', 200)
        ->assertSet('shelfLifeModalOpen', false)
        ->assertNotified('Shelf Life WIP sudah tersedia');
    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])->call('openMemoShelfLifeModal', 1)->assertStatus(422);
    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])->call('openMemoShelfLifeModal', 999)->assertStatus(422);

    $memoOnly = User::factory()->create(['is_active' => true]);
    $memoOnly->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo', 'update rnd internal memo']);
    $this->actingAs($memoOnly);
    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])
        ->assertDontSee('Isi Shelf Life')
        ->call('openMemoShelfLifeModal', 300)
        ->assertForbidden();
    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])->call('saveMemoShelfLife')->assertForbidden();

    expect($master->fresh()->shelf_life_value)->toBe('3.00')
        ->and(RndProductEsbShelfLife::query()->count())->toBe(1);
});

it('opens the Shelf Life and Minimum Order modals from icon buttons with the product and its purchase unit', function () {
    $this->menu->materials()->where('product_code', 'BW212')->update(['purchase_uom_name' => 'PACK@3000GR']);
    $this->menu->materials()->where('product_code', 'RM-BOX')->update(['purchase_uom_name' => 'PACK@100PCS']);
    $this->actingAs($this->filler);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])
        ->assertSeeHtml('aria-label="Isi Shelf Life PRX | CRP02"')
        ->assertSeeHtml('aria-label="Ubah Minimum Order Box Cake"')
        ->assertSee('Belum diisi')
        ->assertSee('Belum ditentukan')
        ->call('openMemoShelfLifeModal', 300)
        ->assertSee('Unit Purchase:')
        ->assertSee('PACK@3000GR')
        ->call('closeShelfLifeModal')
        ->call('editMinimumOrder', 'store|pd:1')
        ->assertSet('minimumOrderTarget.product_name', 'Box Cake')
        ->assertSet('minimumOrderTarget.purchase_uom_name', 'PACK@100PCS')
        ->assertSee('Minimum Order')
        ->assertSee('PACK@100PCS')
        ->set('minimumOrderValue', '-1')
        ->call('saveMinimumOrder')
        ->assertHasErrors(['minimumOrderValue'])
        ->assertSet('minimumOrderKey', 'store|pd:1')
        ->set('minimumOrderValue', '200')
        ->call('saveMinimumOrder')
        ->assertHasNoErrors()
        ->assertSet('minimumOrderTarget', null)
        ->assertSee('200 GR');

    expect((float) $this->menu->materials()->where('product_code', 'RM-BOX')->value('minimum_order'))->toBe(200.0);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])->call('editMinimumOrder', 'store|pd:999')->assertNotFound();
});

it('decides by the Menu company, so a legacy Memo labelled with another company still offers Shelf Life for its BLSS Menus', function () {
    $this->memo->forceFill(['company_code' => 'BLO6'])->save();
    $legacyMenu = $this->memo->menus()->create(['company_code' => 'BLO6', 'esb_menu_id' => 777, 'menu_name' => 'Menu BLO6', 'esb_bom_id' => 77, 'release_date' => now(), 'menu_snapshot' => []]);
    $legacyMenu->materials()->create([
        'esb_product_detail_id' => 900, 'product_code' => 'BW-BLO6', 'product_name' => 'WIP BLO6', 'uom_name' => 'GR', 'quantity_per_menu' => 1,
        'net_quantity' => 0, 'source_bom_id' => 77, 'source_path' => [], 'depth' => 0, 'is_wip' => true, 'is_packaging' => false,
    ]);
    $this->actingAs($this->filler);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])
        ->assertSeeHtml('aria-label="Isi Shelf Life PRX | CRP02"')
        ->call('openMemoShelfLifeModal', 300)
        ->set('shelfLifeValue', '4')
        ->call('saveMemoShelfLife')
        ->assertHasNoErrors();
    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])->call('openMemoShelfLifeModal', 900)->assertStatus(422);

    expect(RndProductEsbShelfLife::query()->pluck('esb_product_detail_id')->all())->toBe([300]);
});

it('exports a Product Active section to xlsx with a WIP and a RAW sheet, for users who can view the Memo', function () {
    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 200, 'shelf_life_value' => 3, 'shelf_life_unit' => 'day', 'storage_condition' => 'chiller']);
    $this->menu->materials()->where('product_code', 'RM-BOX')->update(['purchase_uom_name' => 'PACK@100PCS', 'minimum_order' => 50]);
    $this->actingAs($this->filler);

    $response = $this->get(route('helpdesk.rnd-internal-memos.product-active-export', ['memo' => $this->memo->id, 'scope' => 'store']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml')
        ->and($response->headers->get('content-disposition'))->toContain('product-active-store-');

    $reader = new Reader;
    $reader->open($response->getFile()->getPathname());
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $sheets[$sheet->getName()][] = $row->toArray();
        }
    }
    $reader->close();

    expect(array_keys($sheets))->toBe(['WIP Store', 'RAW Store'])
        ->and($sheets['WIP Store'][6])->toBe(['No', 'Product Code', 'Product Name', 'UOM BOM', 'Purchase UOM', 'Shelf Life', 'Storage', 'Minimum Order', 'UOM Minimum Order'])
        ->and(array_slice($sheets['WIP Store'][7], 1, 2))->toBe(['BW-CREPE', 'Crepe Sheet'])
        ->and($sheets['WIP Store'][7][5])->toBe('3 Hari')
        ->and($sheets['RAW Store'][6])->toBe(['No', 'Product Code', 'Product Name', 'UOM BOM', 'Purchase UOM', 'Minimum Order', 'UOM Minimum Order'])
        ->and($sheets['RAW Store'][7])->toBe([1, 'RM-BOX', 'Box Cake', 'GR', 'PACK@100PCS', 50, 'GR']);

    $outsider = User::factory()->create(['is_active' => true]);
    $this->actingAs($outsider)->get(route('helpdesk.rnd-internal-memos.product-active-export', ['memo' => $this->memo->id, 'scope' => 'kitchen']))->assertForbidden();
    $this->actingAs($this->filler)->get('/rnd-internal-memos/'.$this->memo->id.'/product-active/other/export-xlsx')->assertNotFound();
});
