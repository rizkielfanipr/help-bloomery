<?php

use App\Filament\Helpdesk\Pages\BomAdjustmentPage;
use App\Filament\Helpdesk\Pages\WipShelfLifePage;
use App\Models\RndProject;
use App\Models\RndProjectProduct;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;

/**
 * The old "Shelf Life" URL now serves the WIP Shelf Life menu; the Menu export stays retired
 * (docs/rnd-wip-shelf-life-prd.md §18, §20).
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
});

it('serves the new WIP Shelf Life menu at the old Shelf Life URL', function () {
    $rnd = User::factory()->create(['is_active' => true]);
    $rnd->assignRole('RND_STAFF');
    $this->actingAs($rnd);

    expect(WipShelfLifePage::getUrl(panel: 'helpdesk'))->toBe(url('/shelf-life'));
    $this->get('/shelf-life')->assertSuccessful()->assertSee('Barang WIP');
});

it('gives Design users read-only Shelf Life access without BOM or edit permissions', function () {
    $designer = User::factory()->create(['is_active' => true]);
    $designer->assignRole('DESIGN_STAFF');
    $this->actingAs($designer);

    $this->get('/shelf-life')->assertSuccessful();

    expect($designer->can('view wip shelf life'))->toBeTrue()
        ->and($designer->can('manage wip shelf life'))->toBeFalse()
        ->and($designer->can('view bill of materials'))->toBeFalse()
        ->and($designer->can('edit bill of materials'))->toBeFalse()
        ->and($designer->can('view rnd projects'))->toBeTrue();
});

it('requires authentication for the retired URLs', function () {
    $this->get('/shelf-life')->assertRedirect();
    $this->get(route('helpdesk.exports.shelf-life'))->assertRedirect();
});

it('retires the Shelf Life Menu export with 410 Gone instead of redirecting', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('RND_STAFF');
    $this->actingAs($user);

    $this->get(route('helpdesk.exports.shelf-life'))
        ->assertStatus(410)
        ->assertSee('Export Shelf Life Menu sudah dipensiunkan');
});

it('shows the Shelf Life menu next to Recipe Adjustment in the sidebar only for permitted users', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('RND_STAFF');
    $this->actingAs($user);

    $this->get(BomAdjustmentPage::getUrl(panel: 'helpdesk'))
        ->assertSuccessful()
        ->assertSeeInOrder(['Recipe Adjustment', 'Shelf Life'])
        ->assertSee('href="'.WipShelfLifePage::getUrl(panel: 'helpdesk').'"', false)
        ->assertDontSee('Master Shelf Life Menu');

    $bomOnly = User::factory()->create(['is_active' => true]);
    $bomOnly->givePermissionTo(['access backoffice', 'view bill of materials']);
    $this->actingAs($bomOnly)
        ->get(BomAdjustmentPage::getUrl(panel: 'helpdesk'))
        ->assertSuccessful()
        ->assertDontSee('href="'.WipShelfLifePage::getUrl(panel: 'helpdesk').'"', false);
});

it('keeps historical Menu Shelf Life columns on Products readable', function () {
    $project = RndProject::query()->create(['name' => 'Histori', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01']);
    $product = $project->products()->create([
        'name' => 'Matcha Latte Bottle', 'status' => 'released',
        'shelf_life_value' => 7, 'shelf_life_unit' => 'day', 'storage_condition' => 'chiller', 'storage_notes' => 'Simpan 2-5 derajat.',
    ]);

    expect(RndProjectProduct::query()->find($product->id)->only(['shelf_life_value', 'shelf_life_unit', 'storage_condition', 'storage_notes']))
        ->toBe(['shelf_life_value' => 7, 'shelf_life_unit' => 'day', 'storage_condition' => 'chiller', 'storage_notes' => 'Simpan 2-5 derajat.']);
});
