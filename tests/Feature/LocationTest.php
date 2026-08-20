<?php

use App\Filament\Helpdesk\Resources\Locations\LocationResource;
use App\Filament\Helpdesk\Resources\Locations\Pages\CreateLocation;
use App\Filament\Helpdesk\Resources\Locations\Pages\LocationFloorPlanPage;
use App\Filament\Helpdesk\Resources\LocationTypes\Pages\CreateLocationType;
use App\Models\Branch;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('b2');
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $this->admin->assignRole('SUPERADMIN');
    $this->actingAs($this->admin);
});

it('generates a hierarchical code from parent code and segment', function () {
    $branch = Branch::factory()->create();

    $warehouse = Location::create([
        'branch_id' => $branch->id,
        'name' => 'Gudang Utama',
        'type' => 'Gudang',
        'segment' => 'WH1',
    ]);
    $rack = Location::create([
        'branch_id' => $branch->id,
        'parent_id' => $warehouse->id,
        'name' => 'Rak A',
        'type' => 'Rak',
        'segment' => 'A',
    ]);
    $bin = Location::create([
        'branch_id' => $branch->id,
        'parent_id' => $rack->id,
        'name' => 'Bin 01',
        'type' => 'Bin',
        'segment' => '01',
    ]);

    expect($warehouse->code)->toBe('WH1')->and($warehouse->depth)->toBe(0)
        ->and($rack->code)->toBe('WH1-A')->and($rack->depth)->toBe(1)
        ->and($bin->code)->toBe('WH1-A-01')->and($bin->depth)->toBe(2);
});

it('recomputes descendant codes when a parent is reparented or renamed', function () {
    $branch = Branch::factory()->create();

    $warehouse = Location::create(['branch_id' => $branch->id, 'name' => 'Gudang', 'type' => 'Gudang', 'segment' => 'WH1']);
    $rack = Location::create(['branch_id' => $branch->id, 'parent_id' => $warehouse->id, 'name' => 'Rak A', 'type' => 'Rak', 'segment' => 'A']);
    $bin = Location::create(['branch_id' => $branch->id, 'parent_id' => $rack->id, 'name' => 'Bin 01', 'type' => 'Bin', 'segment' => '01']);

    $warehouse->update(['segment' => 'WHX']);

    expect($rack->refresh()->code)->toBe('WHX-A')
        ->and($bin->refresh()->code)->toBe('WHX-A-01');
});

it('lists locations scoped to the accessible branches of the current user', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    Location::factory()->create(['branch_id' => $branchA->id, 'segment' => 'A1', 'code' => 'A1']);
    Location::factory()->create(['branch_id' => $branchB->id, 'segment' => 'B1', 'code' => 'B1']);

    $scopedUser = User::factory()->create(['is_active' => true, 'access_all_branches' => false, 'branch_id' => $branchA->id]);
    $this->actingAs($scopedUser);

    $ids = LocationResource::getEloquentQuery()->pluck('branch_id');

    expect($ids)->each(fn ($id) => $id->toBe($branchA->id));
});

it('creates a location from the helpdesk form', function () {
    $branch = Branch::factory()->create();

    Livewire::test(CreateLocation::class)
        ->fillForm([
            'branch_id' => $branch->id,
            'name' => 'Zona Cold Storage',
            'type' => 'Zona',
            'segment' => 'WH1',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $location = Location::query()->sole();

    expect($location->name)->toBe('Zona Cold Storage')
        ->and($location->code)->toBe('WH1');
});

it('rejects a segment containing invalid characters', function () {
    $branch = Branch::factory()->create();

    Livewire::test(CreateLocation::class)
        ->fillForm([
            'branch_id' => $branch->id,
            'name' => 'Zona Cold Storage',
            'type' => 'Zona',
            'segment' => 'WH-1',
        ])
        ->call('create')
        ->assertHasFormErrors(['segment']);
});

it('only allows a type value from the managed location types list', function () {
    $branch = Branch::factory()->create();

    Livewire::test(CreateLocation::class)
        ->fillForm([
            'branch_id' => $branch->id,
            'name' => 'Zona Cold Storage',
            'type' => 'Tipe Tidak Terdaftar',
            'segment' => 'WH1',
        ])
        ->call('create')
        ->assertHasFormErrors(['type']);
});

it('manages location types through the location types resource', function () {
    Livewire::test(CreateLocationType::class)
        ->fillForm(['name' => 'Dock', 'sort_order' => 5, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LocationType::where('name', 'Dock')->exists())->toBeTrue();
});

it('prevents deleting a location that still has children', function () {
    $branch = Branch::factory()->create();

    $warehouse = Location::create(['branch_id' => $branch->id, 'name' => 'Gudang', 'type' => 'Gudang', 'segment' => 'WH1']);
    Location::create(['branch_id' => $branch->id, 'parent_id' => $warehouse->id, 'name' => 'Rak A', 'type' => 'Rak', 'segment' => 'A']);

    expect(fn () => $warehouse->delete())->toThrow(QueryException::class);
});

it('generates and stores a qr code svg when the location code changes', function () {
    $branch = Branch::factory()->create();

    $warehouse = Location::create(['branch_id' => $branch->id, 'name' => 'Gudang', 'type' => 'Gudang', 'segment' => 'WH1']);

    expect($warehouse->qr_svg_path)->not->toBeNull();
    Storage::disk('b2')->assertExists($warehouse->qr_svg_path);

    $oldPath = $warehouse->qr_svg_path;
    $warehouse->update(['segment' => 'WHX']);

    expect($warehouse->qr_svg_path)->toBe($oldPath);
    Storage::disk('b2')->assertExists($warehouse->qr_svg_path);
});

it('downloads a single location label pdf', function () {
    $branch = Branch::factory()->create();
    $warehouse = Location::create(['branch_id' => $branch->id, 'name' => 'Gudang', 'type' => 'Gudang', 'segment' => 'WH1']);

    $response = $this->get(route('helpdesk.locations.label-pdf', $warehouse));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('downloads a bulk location labels pdf', function () {
    $branch = Branch::factory()->create();
    $warehouse = Location::create(['branch_id' => $branch->id, 'name' => 'Gudang', 'type' => 'Gudang', 'segment' => 'WH1']);
    $rack = Location::create(['branch_id' => $branch->id, 'parent_id' => $warehouse->id, 'name' => 'Rak A', 'type' => 'Rak', 'segment' => 'A']);

    $response = $this->get(route('helpdesk.locations.labels-pdf', ['ids' => [$warehouse->id, $rack->id]]));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('renders the floor plan page with the branch\'s full nested location tree', function () {
    $branch = Branch::factory()->create();
    $warehouse = Location::create(['branch_id' => $branch->id, 'name' => 'Gudang', 'type' => 'Gudang', 'segment' => 'WH1']);
    Location::create(['branch_id' => $branch->id, 'parent_id' => $warehouse->id, 'name' => 'Rak A', 'type' => 'Rak', 'segment' => 'A']);

    $this->get(route('filament.helpdesk.resources.locations.floor-plan', ['branch_id' => $branch->id]))
        ->assertOk()
        ->assertSee('Gudang')
        ->assertSee('Rak A')
        ->assertSee('WH1-A');

    $this->get(route('filament.helpdesk.resources.locations.floor-plan'))
        ->assertOk()
        ->assertSee($branch->name);
});

it('creates a root location and a nested child location from the floor plan page', function () {
    $branch = Branch::factory()->create();
    $warehouse = Location::create(['branch_id' => $branch->id, 'name' => 'Gudang', 'type' => 'Gudang', 'segment' => 'WH1']);

    $child = Livewire::test(LocationFloorPlanPage::class, ['branchId' => $branch->id])
        ->instance()
        ->createChild(['name' => 'Rak A', 'type' => 'Rak', 'segment' => 'A', 'parent_id' => $warehouse->id]);

    expect($child['code'])->toBe('WH1-A')
        ->and($child['parent_id'])->toBe($warehouse->id);

    $root = Livewire::test(LocationFloorPlanPage::class, ['branchId' => $branch->id])
        ->instance()
        ->createChild(['name' => 'Zona Baru', 'type' => 'Zona', 'segment' => 'ZB', 'parent_id' => null]);

    expect($root['code'])->toBe('ZB')
        ->and($root['parent_id'])->toBeNull();
});

it('rejects a duplicate segment among siblings on the floor plan page', function () {
    $branch = Branch::factory()->create();
    $warehouse = Location::create(['branch_id' => $branch->id, 'name' => 'Gudang', 'type' => 'Gudang', 'segment' => 'WH1']);
    Location::create(['branch_id' => $branch->id, 'parent_id' => $warehouse->id, 'name' => 'Rak A', 'type' => 'Rak', 'segment' => 'A']);

    Livewire::test(LocationFloorPlanPage::class, ['branchId' => $branch->id])
        ->call('createChild', ['name' => 'Rak A Duplikat', 'type' => 'Rak', 'segment' => 'A', 'parent_id' => $warehouse->id])
        ->assertHasErrors();
});

it('rejects creating a child under a parent outside the current branch', function () {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $foreignParent = Location::create(['branch_id' => $otherBranch->id, 'name' => 'Gudang Lain', 'type' => 'Gudang', 'segment' => 'WH2']);

    Livewire::test(LocationFloorPlanPage::class, ['branchId' => $branch->id])
        ->call('createChild', ['name' => 'Rak', 'type' => 'Rak', 'segment' => 'A', 'parent_id' => $foreignParent->id])
        ->assertForbidden();
});

it('saves floor plan layout positions for every level in the branch tree', function () {
    $branch = Branch::factory()->create();
    $warehouse = Location::create(['branch_id' => $branch->id, 'name' => 'Gudang', 'type' => 'Gudang', 'segment' => 'WH1']);
    $rack = Location::create(['branch_id' => $branch->id, 'parent_id' => $warehouse->id, 'name' => 'Rak A', 'type' => 'Rak', 'segment' => 'A']);

    Livewire::test(LocationFloorPlanPage::class, ['branchId' => $branch->id])
        ->call('saveLayout', [
            ['id' => $warehouse->id, 'pos_x' => 10, 'pos_y' => 10, 'width' => 80, 'height' => 80],
            ['id' => $rack->id, 'pos_x' => 20, 'pos_y' => 15, 'width' => 30, 'height' => 12],
        ]);

    $rack->refresh();

    expect((float) $rack->pos_x)->toBe(20.0)
        ->and((float) $rack->pos_y)->toBe(15.0)
        ->and((float) $rack->width)->toBe(30.0)
        ->and((float) $rack->height)->toBe(12.0);
});

it('rejects saving layout positions for a location outside the current branch', function () {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $foreign = Location::create(['branch_id' => $otherBranch->id, 'name' => 'Gudang Lain', 'type' => 'Gudang', 'segment' => 'WH2']);

    Livewire::test(LocationFloorPlanPage::class, ['branchId' => $branch->id])
        ->call('saveLayout', [
            ['id' => $foreign->id, 'pos_x' => 1, 'pos_y' => 1, 'width' => 40, 'height' => 40],
        ])
        ->assertForbidden();
});

it('deletes a childless location node from the floor plan page', function () {
    $branch = Branch::factory()->create();
    $warehouse = Location::create(['branch_id' => $branch->id, 'name' => 'Gudang', 'type' => 'Gudang', 'segment' => 'WH1']);
    $rack = Location::create(['branch_id' => $branch->id, 'parent_id' => $warehouse->id, 'name' => 'Rak A', 'type' => 'Rak', 'segment' => 'A']);

    $deleted = Livewire::test(LocationFloorPlanPage::class, ['branchId' => $branch->id])
        ->instance()
        ->deleteNode($rack->id);

    expect($deleted)->toBeTrue();
    expect(Location::find($rack->id))->toBeNull();
});

it('refuses to delete a location node that still has children on the floor plan page', function () {
    $branch = Branch::factory()->create();
    $warehouse = Location::create(['branch_id' => $branch->id, 'name' => 'Gudang', 'type' => 'Gudang', 'segment' => 'WH1']);
    $rack = Location::create(['branch_id' => $branch->id, 'parent_id' => $warehouse->id, 'name' => 'Rak A', 'type' => 'Rak', 'segment' => 'A']);
    Location::create(['branch_id' => $branch->id, 'parent_id' => $rack->id, 'name' => 'Bin 01', 'type' => 'Bin', 'segment' => '01']);

    $deleted = Livewire::test(LocationFloorPlanPage::class, ['branchId' => $branch->id])
        ->instance()
        ->deleteNode($rack->id);

    expect($deleted)->toBeFalse();
    expect(Location::find($rack->id))->not->toBeNull();
});

it('renders the location menu link in the custom helpdesk sidebar', function () {
    $response = $this->get(route('filament.helpdesk.resources.locations.index'));

    $response->assertOk()
        ->assertSee('Lokasi')
        ->assertSee(route('filament.helpdesk.resources.locations.index'), false);
});
