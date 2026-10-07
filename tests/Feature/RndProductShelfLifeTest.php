<?php

use App\Filament\Helpdesk\Pages\BomAdjustmentPage;
use App\Filament\Helpdesk\Pages\WipShelfLifePage;
use App\Models\RndProductEsbShelfLife;
use App\Models\User;
use App\Services\Rnd\ShelfLife\WipShelfLifeResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;

/**
 * Retirement contract of the old "Master Shelf Life Menu" (docs/rnd-wip-shelf-life-prd.md §19, §20).
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->bomViewer = User::factory()->create(['is_active' => true]);
    $this->bomViewer->givePermissionTo(['access backoffice', 'view bill of materials']);
});

it('keeps legacy Menu master rows readable but never resolves them as WIP masters', function () {
    $legacy = RndProductEsbShelfLife::factory()->legacyMenu()->create([
        'esb_menu_id' => 501, 'product_name' => 'Croissant Butter', 'shelf_life_value' => 3,
        'shelf_life_unit' => 'hari', 'storage_condition' => 'Chiller 2-8°C',
    ]);

    expect($legacy->fresh()->shelfLifeLabel())->toBe('3 Hari')
        ->and($legacy->fresh()->storageConditionLabel())->toBe('Chiller 2-8°C')
        ->and(app(WipShelfLifeResolver::class)->masters([501]))->toBeEmpty()
        ->and(method_exists(RndProductEsbShelfLife::class, 'forMenu'))->toBeFalse();
});

it('redirects every retired Master Shelf Life Menu URL to the WIP Shelf Life menu without a Menu identity', function (string $path) {
    $legacy = RndProductEsbShelfLife::factory()->legacyMenu()->create(['esb_menu_id' => 501]);
    $this->actingAs($this->bomViewer);

    $response = $this->get(str_replace('{record}', (string) $legacy->id, $path));

    $response->assertRedirect(WipShelfLifePage::getUrl(['shelfLifeFilter' => 'missing'], panel: 'helpdesk'));
    expect($response->headers->get('Location'))->not->toContain('501')
        ->not->toContain((string) $legacy->id.'/');
})->with([
    'index' => ['/rnd-product-shelf-lives'],
    'create' => ['/rnd-product-shelf-lives/create'],
    'edit' => ['/rnd-product-shelf-lives/{record}/edit'],
]);

it('lets the destination authorize the redirect: users without Shelf Life access are refused there', function () {
    $this->actingAs($this->bomViewer);

    $this->followingRedirects()->get('/rnd-product-shelf-lives')->assertForbidden();
});

it('removes the Master Shelf Life Menu link from the sidebar and keeps Recipe Adjustment', function () {
    $rnd = User::factory()->create(['is_active' => true]);
    $rnd->assignRole('RND_STAFF');
    $this->actingAs($rnd);

    $this->get(BomAdjustmentPage::getUrl(panel: 'helpdesk'))
        ->assertSuccessful()
        ->assertDontSee('Master Shelf Life Menu')
        ->assertSee('Recipe Adjustment')
        ->assertDontSee('/rnd-product-shelf-lives', false);
});
