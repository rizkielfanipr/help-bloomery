<?php

use App\Filament\Helpdesk\Pages\ShelfLifePage;
use App\Models\RndProject;
use App\Models\RndProjectProduct;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
});

it('shows and filters shelf life products', function () {
    $project = RndProject::query()->create([
        'name' => 'Project Minuman Baru',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => auth()->id(),
    ]);

    RndProjectProduct::query()->create([
        'rnd_project_id' => $project->id,
        'name' => 'Matcha Latte Bottle',
        'product_code' => 'SKU-MATCHA-01',
        'shelf_life_value' => 7,
        'shelf_life_unit' => 'day',
        'storage_condition' => 'chiller',
        'storage_notes' => 'Simpan pada suhu 2-5 derajat.',
        'status' => 'ready',
        'created_by' => auth()->id(),
    ]);

    RndProjectProduct::query()->create([
        'rnd_project_id' => $project->id,
        'name' => 'Dry Cookie',
        'product_code' => 'SKU-COOKIE-01',
        'storage_condition' => 'dry',
        'status' => 'draft',
        'created_by' => auth()->id(),
    ]);

    Livewire::test(ShelfLifePage::class)
        ->assertSee('Matcha Latte Bottle')
        ->assertSee('SKU-MATCHA-01')
        ->assertSee('7 Hari')
        ->set('search', 'COOKIE')
        ->assertSee('Dry Cookie')
        ->assertDontSee('Matcha Latte Bottle');
});

it('exports shelf life products to an xlsx file', function () {
    $project = RndProject::query()->create([
        'name' => 'Project Export',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => auth()->id(),
    ]);

    RndProjectProduct::query()->create([
        'rnd_project_id' => $project->id,
        'name' => 'Exported Product',
        'product_code' => 'SKU-EXPORT-01',
        'shelf_life_value' => 3,
        'shelf_life_unit' => 'month',
        'storage_condition' => 'frozen',
        'status' => 'released',
        'created_by' => auth()->id(),
    ]);

    $response = $this->get(route('helpdesk.exports.shelf-life'));

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect($response->headers->get('content-disposition'))->toContain('shelf-life-products-');
});

it('updates missing shelf life data from the shelf life page', function () {
    $project = RndProject::query()->create([
        'name' => 'Project Shelf Life Input',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => auth()->id(),
    ]);
    $product = RndProjectProduct::query()->create([
        'rnd_project_id' => $project->id,
        'name' => 'Product Belum Diatur',
        'product_code' => 'SKU-SHELF-01',
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);

    Livewire::test(ShelfLifePage::class)
        ->assertSee('Isi Shelf Life')
        ->call('editShelfLife', $product->id)
        ->assertSet('shelfLifeModalOpen', true)
        ->assertSet('editingProductName', 'Product Belum Diatur')
        ->set('shelfLifeValue', '14')
        ->set('shelfLifeUnit', 'day')
        ->set('editingStorageCondition', 'chiller')
        ->set('storageNotes', 'Simpan pada suhu 2-5°C.')
        ->call('saveShelfLife')
        ->assertHasNoErrors()
        ->assertSet('shelfLifeModalOpen', false)
        ->assertSee('14 Hari');

    $product->refresh();
    expect($product->shelf_life_value)->toBe(14)
        ->and($product->shelf_life_unit)->toBe('day')
        ->and($product->storage_condition)->toBe('chiller')
        ->and($product->storage_notes)->toBe('Simpan pada suhu 2-5°C.');
});
