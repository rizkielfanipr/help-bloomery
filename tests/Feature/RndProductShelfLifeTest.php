<?php

use App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\Pages\CreateRndProductEsbShelfLife;
use App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\Pages\EditRndProductEsbShelfLife;
use App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\Pages\ListRndProductEsbShelfLives;
use App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\RndProductEsbShelfLifeResource;
use App\Models\RndProductEsbShelfLife;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->givePermissionTo('manage rnd product shelf life');
    $this->actingAs($this->manager);
});

it('creates a local shelf life master for a Menu', function () {
    Livewire::test(CreateRndProductEsbShelfLife::class)
        ->fillForm([
            'product_name' => 'Croissant Butter',
            'esb_menu_id' => 501,
            'shelf_life_value' => 3,
            'shelf_life_unit' => 'hari',
            'storage_condition' => 'Chiller 2-8°C',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $shelfLife = RndProductEsbShelfLife::sole();
    expect($shelfLife->company_code)->toBe('BLSS')
        ->and($shelfLife->esb_menu_id)->toBe(501)
        ->and((float) $shelfLife->shelf_life_value)->toBe(3.0)
        ->and($shelfLife->created_by)->toBe($this->manager->id)
        ->and($shelfLife->is_active)->toBeTrue();
});

it('finds the active master matching a Menu by company and menu id, ignoring inactive rows', function () {
    RndProductEsbShelfLife::factory()->create(['esb_menu_id' => 501, 'is_active' => false]);
    $active = RndProductEsbShelfLife::factory()->create(['esb_menu_id' => 501, 'is_active' => true]);
    RndProductEsbShelfLife::factory()->create(['esb_menu_id' => 999, 'is_active' => true]);

    expect(RndProductEsbShelfLife::forMenu('BLSS', 501)->id)->toBe($active->id)
        ->and(RndProductEsbShelfLife::forMenu('BLSS', 12345))->toBeNull();
});

it('lists, edits, and deletes a shelf life master', function () {
    $shelfLife = RndProductEsbShelfLife::factory()->create(['product_name' => 'Croissant Butter']);

    Livewire::test(ListRndProductEsbShelfLives::class)->assertSee('Croissant Butter');

    Livewire::test(EditRndProductEsbShelfLife::class, ['record' => $shelfLife->id])
        ->fillForm(['shelf_life_value' => 5])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((float) $shelfLife->fresh()->shelf_life_value)->toBe(5.0)
        ->and($shelfLife->fresh()->updated_by)->toBe($this->manager->id);

    Livewire::test(EditRndProductEsbShelfLife::class, ['record' => $shelfLife->id])
        ->callAction('delete');

    expect(RndProductEsbShelfLife::withTrashed()->findOrFail($shelfLife->id)->trashed())->toBeTrue();
});

it('hides the master shelf life screen from users without the permission', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $this->actingAs($viewer);

    expect(RndProductEsbShelfLifeResource::canViewAny())->toBeFalse();

    Livewire::test(ListRndProductEsbShelfLives::class)->assertForbidden();
});

it('renders the Master Shelf Life Menu link in the custom helpdesk sidebar', function () {
    $this->manager->givePermissionTo('access backoffice');

    $response = $this->get(route('filament.helpdesk.resources.rnd-product-shelf-lives.index'));

    $response->assertOk()
        ->assertSee('Research & Development')
        ->assertSee('Master Shelf Life Menu')
        ->assertSee(route('filament.helpdesk.resources.rnd-product-shelf-lives.index'), false);
});
