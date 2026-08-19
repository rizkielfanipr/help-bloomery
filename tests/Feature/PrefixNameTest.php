<?php

use App\Filament\Helpdesk\Resources\PrefixNames\Pages\CreatePrefixName;
use App\Filament\Helpdesk\Resources\PrefixNames\Pages\EditPrefixName;
use App\Filament\Helpdesk\Resources\PrefixNames\Pages\ListPrefixNames;
use App\Models\PrefixName;
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

it('seeds the previously hardcoded WIP naming prefixes via migration', function () {
    expect(PrefixName::pluck('label', 'code')->all())->toBe([
        'WIP |' => 'Kitchen - WIP |',
        'JYM |' => 'Joy-Mart - JYM |',
        'ATL |' => 'Atelier - ATL |',
        'EGG |' => 'Eggish - EGG |',
        'Central |' => 'Patisserie - Central |',
    ]);
});

it('lists prefix names ordered by sort_order', function () {
    PrefixName::query()->delete();
    PrefixName::create(['code' => 'B |', 'label' => 'Second', 'sort_order' => 2]);
    PrefixName::create(['code' => 'A |', 'label' => 'First', 'sort_order' => 1]);

    Livewire::test(ListPrefixNames::class)
        ->assertSee('First')
        ->assertSee('Second');
});

it('creates a prefix name', function () {
    Livewire::test(CreatePrefixName::class)
        ->fillForm([
            'code' => 'NEW |',
            'label' => 'New Brand - NEW |',
            'sort_order' => 6,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PrefixName::where('code', 'NEW |')->exists())->toBeTrue();
});

it('requires a unique code when creating a prefix name', function () {
    PrefixName::create(['code' => 'DUP |', 'label' => 'Duplicate', 'sort_order' => 1]);

    Livewire::test(CreatePrefixName::class)
        ->fillForm([
            'code' => 'DUP |',
            'label' => 'Another Label',
        ])
        ->call('create')
        ->assertHasFormErrors(['code']);
});

it('edits a prefix name', function () {
    $prefixName = PrefixName::create(['code' => 'OLD |', 'label' => 'Old Label', 'sort_order' => 1]);

    Livewire::test(EditPrefixName::class, ['record' => $prefixName->getRouteKey()])
        ->fillForm(['label' => 'Updated Label'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($prefixName->refresh()->label)->toBe('Updated Label');
});

it('deletes a prefix name from the index', function () {
    $prefixName = PrefixName::create(['code' => 'DEL |', 'label' => 'To Delete', 'sort_order' => 1]);

    Livewire::test(ListPrefixNames::class)
        ->callTableAction('delete', $prefixName)
        ->assertHasNoActionErrors();

    expect(PrefixName::find($prefixName->id))->toBeNull();
});

it('renders the prefix name link in the custom helpdesk sidebar', function () {
    $response = $this->get(route('filament.helpdesk.resources.prefix-names.index'));

    $response->assertOk()
        ->assertSee('Research & Development')
        ->assertSee(route('filament.helpdesk.resources.prefix-names.index'), false);
});
