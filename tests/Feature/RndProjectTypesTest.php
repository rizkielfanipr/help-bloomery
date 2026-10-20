<?php

use App\Actions\Rnd\Project\AddPartnershipMenusAction;
use App\Actions\Rnd\Project\AddPremixAssembliesAction;
use App\Enums\RndProjectType;
use App\Filament\Helpdesk\Resources\Projects\Pages\ListProjects;
use App\Filament\Helpdesk\Resources\Projects\Pages\ViewProject;
use App\Models\Branch;
use App\Models\RndBomCatalog;
use App\Models\RndInternalMemoCatalogSync;
use App\Models\RndInternalMemoMenuCatalog;
use App\Models\RndProject;
use App\Models\User;
use App\Services\EsbMasterProductService;
use App\Services\EsbService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->assignRole('SUPERADMIN');
    $this->actingAs($this->user);
    Http::preventStrayRequests();
});

it('keeps legacy projects as release product by default', function () {
    $project = RndProject::query()->create(['name' => 'Legacy Release Project', 'start_date' => today(), 'end_date' => today()->addMonth()]);

    expect($project->fresh()->project_type)->toBe(RndProjectType::ReleaseProduct)
        ->and($project->partnership_event_name)->toBeNull();

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->assertSee('Release Product')->assertSee('Critical Control Point (CCP)')
        ->assertDontSee('BOM Assembly')->assertDontSee('Menu Partnership');
});

it('creates partnership projects and requires an event name', function () {
    $firstBranch = Branch::factory()->create(['is_active' => true]);
    $secondBranch = Branch::factory()->create(['is_active' => true]);

    Livewire::test(ListProjects::class)
        ->call('openCreateProjectModal')->set('projectName', 'Invalid Partnership')
        ->set('projectType', RndProjectType::Partnership->value)
        ->set('projectEndDate', today()->addMonth()->toDateString())
        ->call('saveProject')->assertHasErrors(['partnershipEventName' => 'required']);

    Livewire::test(ListProjects::class)
        ->call('openCreateProjectModal')->set('projectName', 'Partnership Project')
        ->set('projectType', RndProjectType::Partnership->value)
        ->set('partnershipEventName', 'Bloomery Partner Day')
        ->call('togglePartnershipBranchId', $firstBranch->id)
        ->call('togglePartnershipBranchId', $secondBranch->id)
        ->set('projectEndDate', today()->addMonth()->toDateString())
        ->call('saveProject')->assertHasNoErrors();

    $project = RndProject::query()->where('name', 'Partnership Project')->sole();
    expect($project->project_type)->toBe(RndProjectType::Partnership)
        ->and($project->partnership_event_name)->toBe('Bloomery Partner Day')
        ->and($project->partnershipBranches()->pluck('branches.id')->sort()->values()->all())
        ->toBe(collect([$firstBranch->id, $secondBranch->id])->sort()->values()->all());
});

it('does not allow a tampered edit request to change project type', function () {
    $project = RndProject::factory()->premix()->create();

    Livewire::test(ListProjects::class)
        ->call('openEditProjectModal', $project->id)
        ->set('projectType', RndProjectType::Partnership->value)
        ->set('projectName', 'Updated Premix')->call('saveProject')->assertHasNoErrors();

    expect($project->fresh()->project_type)->toBe(RndProjectType::Premix)
        ->and($project->partnership_event_name)->toBeNull();
});

it('only adds active assembly catalogs to premix without creating products', function () {
    $project = RndProject::factory()->premix()->create();
    $assembly = RndBomCatalog::factory()->create(['bom_type_id' => 1, 'is_active' => true]);
    $nonAssembly = RndBomCatalog::factory()->create(['bom_type_id' => 2, 'is_active' => true]);

    $added = app(AddPremixAssembliesAction::class)->execute($this->user, $project, [$assembly->id]);
    app(AddPremixAssembliesAction::class)->execute($this->user, $project, [$assembly->id]);

    expect($added)->toBe(1)->and($project->premixAssemblies()->count())->toBe(1)
        ->and($project->premixAssemblies()->sole()->bom_type_id)->toBe(1)
        ->and($project->products()->count())->toBe(0);

    expect(fn () => app(AddPremixAssembliesAction::class)->execute($this->user, $project, [$nonAssembly->id]))
        ->toThrow(ValidationException::class);
});

it('allows the same assembly in different projects but not twice in one project', function () {
    $first = RndProject::factory()->premix()->create();
    $second = RndProject::factory()->premix()->create();
    $assembly = RndBomCatalog::factory()->create();

    app(AddPremixAssembliesAction::class)->execute($this->user, $first, [$assembly->id]);
    app(AddPremixAssembliesAction::class)->execute($this->user, $second, [$assembly->id]);
    app(AddPremixAssembliesAction::class)->execute($this->user, $second, [$assembly->id]);

    expect($first->premixAssemblies()->count())->toBe(1)->and($second->premixAssemblies()->count())->toBe(1);
});

it('adds lightweight partnership snapshots from the active local catalog', function () {
    RndInternalMemoCatalogSync::query()->create([
        'company_code' => 'BLSS', 'technical_branch_code' => 'BLS',
        'status' => RndInternalMemoCatalogSync::STATUS_SUCCESS, 'menu_count' => 1, 'last_synced_at' => now(),
    ]);
    $catalog = RndInternalMemoMenuCatalog::factory()->create(['company_code' => 'BLSS', 'branch_code' => 'BLS', 'menu_name' => 'Partnership Cake']);
    $project = RndProject::factory()->partnership()->create();

    $added = app(AddPartnershipMenusAction::class)->execute($this->user, $project, [$catalog->id]);
    app(AddPartnershipMenusAction::class)->execute($this->user, $project, [$catalog->id]);
    $snapshot = $project->partnershipMenus()->sole();

    expect($added)->toBe(1)->and($project->partnershipMenus()->count())->toBe(1)
        ->and($snapshot->menu_name)->toBe('Partnership Cake')->and($snapshot->company_code)->toBe('BLSS')
        ->and($snapshot->branch_code)->toBe('BLS')->and($snapshot->menu_snapshot)->toHaveKeys(['bom_id', 'bom_name', 'synced_at']);

    $catalog->update(['menu_name' => 'Renamed Catalog Menu', 'flag_active' => false]);
    expect($snapshot->fresh()->menu_name)->toBe('Partnership Cake');
});

it('renders only the workspace belonging to the project type', function () {
    $premix = RndProject::factory()->premix()->create(['name' => 'Premix Workspace']);
    $partnership = RndProject::factory()->partnership()->create(['name' => 'Partnership Workspace']);

    Livewire::test(ViewProject::class, ['record' => $premix->id])
        ->assertSee('BOM Assembly')->assertDontSee('Critical Control Point (CCP)')->assertDontSee('Menu Partnership');

    Livewire::test(ViewProject::class, ['record' => $partnership->id])
        ->assertSee('Menu Partnership')->assertSee('Menu Release')->assertSee('Informasi Tambahan')
        ->assertDontSee('Critical Control Point (CCP)')->assertDontSee('BOM Assembly');
});

it('creates and edits a simple Menu Release with additional information inside Partnership', function () {
    $partnership = RndProject::factory()->partnership()->create();
    $jakarta = Branch::factory()->create(['name' => 'Jakarta Selatan', 'is_active' => true]);
    $surabaya = Branch::factory()->create(['name' => 'Surabaya', 'is_active' => true]);
    $partnership->partnershipBranches()->attach([$jakarta->id, $surabaya->id]);

    $component = Livewire::test(ViewProject::class, ['record' => $partnership->id])
        ->call('openCreateProduct')
        ->set('productName', 'Partnership Special Cake')
        ->set('productCode', 'PSC-001')
        ->set('productDescription', 'Disajikan khusus untuk meja VIP event.')
        ->set('offlinePrice', '75000')
        ->set('onlinePrice', '80000')
        ->set('releaseDate', today()->addWeek()->toDateString())
        ->set('productStatus', 'draft')
        ->call('savePartnershipReleaseMenu')
        ->assertHasNoErrors()
        ->assertSee('Partnership Special Cake')
        ->assertSee('Disajikan khusus untuk meja VIP event.');

    $menu = $partnership->products()->sole();
    expect($menu->product_code)->toBe('PSC-001')
        ->and($menu->description)->toBe('Disajikan khusus untuk meja VIP event.')
        ->and((float) $menu->offline_price)->toBe(75000.0)
        ->and((float) $menu->online_price)->toBe(80000.0);

    $component->call('editProduct', $menu->id)
        ->set('productDescription', 'Informasi tambahan diperbarui.')
        ->call('savePartnershipReleaseMenu')
        ->assertHasNoErrors();

    expect($menu->fresh()->description)->toBe('Informasi tambahan diperbarui.')
        ->and($partnership->partnershipBranches()->count())->toBe(2);
});

it('adds updates and removes Partnership event equipment from the shared Product List picker', function () {
    config()->set('esb.master_product.base_url', 'https://master-product.test');
    config()->set('esb.master_product.token', 'master-token');
    config()->set('esb.core.base_url', 'https://core-esb.test');
    config()->set('esb.core.username', 'global-user');
    config()->set('esb.core.password', 'global-password');
    Cache::forget('esb_core.access_token');
    Cache::forget('esb_core.product_taxonomy');
    $productResponse = ['status' => 'ok', 'result' => [
        'page' => 1,
        'limit' => 10,
        'count' => 1,
        'next' => '',
        'data' => [[
            'productCode' => 'EQ-001',
            'productName' => 'Portable Display Stand',
            'categoryID' => 77,
            'categoryName' => 'Peralatan',
            'productDetails' => [['productDetailID' => 901, 'unit' => 'PCS', 'flagActive' => true]],
        ]],
    ]];
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/list*' => Http::response($productResponse),
        'https://master-product.test/corev1/master/product*' => Http::response($productResponse),
    ]);
    $partnership = RndProject::factory()->partnership()->create();

    $component = Livewire::test(ViewProject::class, ['record' => $partnership->id])
        ->call('openPartnershipEquipmentPicker')
        ->assertSet('inlineProductCategoryId', '77')
        ->set('inlineProductOptions', [901 => [
            'productDetailID' => 901,
            'productCode' => 'EQ-001',
            'productName' => 'Portable Display Stand',
            'unit' => 'PCS',
            'categoryName' => 'Peralatan',
            'subCategoryName' => 'Event',
            'conversionFactor' => 1,
            'baseUnit' => 'PCS',
        ]])
        ->call('selectPartnershipEquipment', 901)
        ->assertHasNoErrors()
        ->assertSee('Portable Display Stand');

    $equipment = $partnership->partnershipEquipment()->sole();
    $component->set("partnershipEquipmentDrafts.{$equipment->id}.quantity", '3')
        ->set("partnershipEquipmentDrafts.{$equipment->id}.notes", 'Untuk area kasir event')
        ->call('savePartnershipEquipment', $equipment->id)
        ->assertHasNoErrors();

    expect((float) $equipment->fresh()->quantity)->toBe(3.0)
        ->and($equipment->fresh()->notes)->toBe('Untuk area kasir event');

    $component->call('removePartnershipEquipment', $equipment->id);
    expect($partnership->partnershipEquipment()->count())->toBe(0);
});

it('rejects non-equipment products submitted to Partnership event equipment', function () {
    $masterProductService = Mockery::mock(EsbMasterProductService::class);
    $masterProductService->shouldReceive('getProductTaxonomy')->once()->andReturn([
        'categories' => [77 => 'Peralatan'],
        'subCategories' => [],
    ]);
    app()->instance(EsbMasterProductService::class, $masterProductService);

    $esbService = Mockery::mock(EsbService::class);
    $esbService->shouldReceive('getAllActiveProductUnits')->once()->andReturn(['PCS']);
    $esbService->shouldReceive('findActiveProductDetail')->once()->andReturn([
        'productDetailID' => 902,
        'productCode' => 'BB-001',
        'productName' => 'Bahan Non Peralatan',
        'categoryName' => 'Bahan Baku',
        'unit' => 'PCS',
    ]);
    app()->instance(EsbService::class, $esbService);

    $partnership = RndProject::factory()->partnership()->create();

    Livewire::test(ViewProject::class, ['record' => $partnership->id])
        ->call('openPartnershipEquipmentPicker')
        ->set('inlineProductOptions', [902 => [
            'productDetailID' => 902,
            'productCode' => 'BB-001',
            'productName' => 'Bahan Non Peralatan',
            'categoryName' => 'Bahan Baku',
            'subCategoryName' => '',
            'unit' => 'PCS',
            'conversionFactor' => 1,
            'baseUnit' => 'PCS',
        ]])
        ->call('selectPartnershipEquipment', 902)
        ->assertStatus(422);

    expect($partnership->partnershipEquipment()->count())->toBe(0);
});

it('enforces edit permission inside child mutation actions', function () {
    $unauthorizedUser = User::factory()->create(['is_active' => true]);
    $premix = RndProject::factory()->premix()->create();
    $assembly = RndBomCatalog::factory()->create();

    expect(fn () => app(AddPremixAssembliesAction::class)->execute($unauthorizedUser, $premix, [$assembly->id]))
        ->toThrow(AuthorizationException::class);
});

it('opens paginated local catalog pickers without external requests', function () {
    $assembly = RndBomCatalog::factory()->create(['bom_name' => 'Picker Premix Assembly']);
    $premix = RndProject::factory()->premix()->create();

    Livewire::test(ViewProject::class, ['record' => $premix->id])
        ->call('openPremixPicker')
        ->assertSee($assembly->bom_name);

    RndInternalMemoCatalogSync::query()->create([
        'company_code' => 'BLSS', 'technical_branch_code' => 'BLS',
        'status' => RndInternalMemoCatalogSync::STATUS_SUCCESS, 'menu_count' => 1, 'last_synced_at' => now(),
    ]);
    $menu = RndInternalMemoMenuCatalog::factory()->create([
        'company_code' => 'BLSS', 'branch_code' => 'BLS', 'menu_name' => 'Picker Partnership Menu',
    ]);
    $partnership = RndProject::factory()->partnership()->create();

    Livewire::test(ViewProject::class, ['record' => $partnership->id])
        ->call('openPartnershipMenuPicker')
        ->assertSee($menu->menu_name);
});
