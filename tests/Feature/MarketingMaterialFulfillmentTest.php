<?php

use App\Enums\MarketingMaterialFulfillmentStatus;
use App\Filament\Helpdesk\Pages\ViewProjectProductPage;
use App\Filament\Helpdesk\Resources\MarketingMaterialFulfillments\Pages\ListMarketingMaterialFulfillments;
use App\Filament\Helpdesk\Resources\MarketingMaterialFulfillments\Pages\ListMarketingMaterialFulfillmentsToReceive;
use App\Models\Branch;
use App\Models\Location;
use App\Models\RndProject;
use App\Models\RndProjectMarketingMaterial;
use App\Models\RndProjectProduct;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $project = RndProject::create(['name' => 'Project A', 'start_date' => now(), 'end_date' => now()->addMonths(3)]);
    $product = RndProjectProduct::create(['rnd_project_id' => $project->id, 'name' => 'Produk A']);

    $this->physicalMaterial = RndProjectMarketingMaterial::create([
        'rnd_project_product_id' => $product->id,
        'type' => 'packaging_design',
        'title' => 'Desain Kemasan',
        'file_path' => 'rnd/marketing-materials/1/1/packaging.pdf',
        'original_name' => 'packaging.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
    ]);

    $this->digitalMaterial = RndProjectMarketingMaterial::create([
        'rnd_project_product_id' => $product->id,
        'type' => 'social_media',
        'title' => 'Post Instagram',
        'file_path' => 'rnd/marketing-materials/1/1/post.jpg',
        'original_name' => 'post.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 2048,
    ]);

    $this->purchasing = User::factory()->create(['is_active' => true]);
    $this->purchasing->assignRole('PURCHASING_STAFF');

    $this->inventory = User::factory()->create(['is_active' => true]);
    $this->inventory->assignRole('INVENTORY_STAFF');

    $this->design = User::factory()->create(['is_active' => true]);
    $this->design->assignRole('DESIGN_STAFF');
});

it('only lists physical material types, not purely digital ones', function () {
    $this->actingAs($this->purchasing);

    Livewire::test(ListMarketingMaterialFulfillments::class)
        ->assertSee('Desain Kemasan')
        ->assertDontSee('Post Instagram');
});

it('lets purchasing mark a physical material as ordered', function () {
    $this->actingAs($this->purchasing);

    Livewire::test(ListMarketingMaterialFulfillments::class)
        ->callTableAction('mark_ordered', $this->physicalMaterial, data: [
            'vendor_name' => 'CV Percetakan Jaya',
            'order_date' => now()->toDateString(),
            'estimated_completion_date' => now()->addDays(7)->toDateString(),
            'purchasing_notes' => 'Dikerjakan bertahap',
        ])
        ->assertHasNoTableActionErrors();

    $fulfillment = $this->physicalMaterial->fulfillment()->sole();

    expect($fulfillment->status)->toBe(MarketingMaterialFulfillmentStatus::Ordered)
        ->and($fulfillment->vendor_name)->toBe('CV Percetakan Jaya')
        ->and($fulfillment->ordered_by)->toBe($this->purchasing->id);
});

it('lets inventory mark an ordered material as received with a storage location', function () {
    $branch = Branch::factory()->create();
    $location = Location::create(['branch_id' => $branch->id, 'name' => 'Gudang Marketing', 'type' => 'Gudang', 'segment' => 'GM']);

    $this->physicalMaterial->fulfillment()->create([
        'status' => MarketingMaterialFulfillmentStatus::Ordered,
        'vendor_name' => 'CV Percetakan Jaya',
        'order_date' => now(),
        'ordered_by' => $this->purchasing->id,
        'ordered_at' => now(),
    ]);

    $this->actingAs($this->inventory);

    Livewire::test(ListMarketingMaterialFulfillmentsToReceive::class)
        ->callTableAction('mark_received', $this->physicalMaterial, data: [
            'received_quantity' => 500,
            'received_date' => now()->toDateString(),
            'location_id' => $location->id,
            'inventory_notes' => 'Diterima lengkap',
        ])
        ->assertHasNoTableActionErrors();

    $fulfillment = $this->physicalMaterial->fulfillment()->sole();

    expect($fulfillment->status)->toBe(MarketingMaterialFulfillmentStatus::Received)
        ->and($fulfillment->received_quantity)->toBe(500)
        ->and($fulfillment->location_id)->toBe($location->id)
        ->and($fulfillment->received_by)->toBe($this->inventory->id);
});

it('keeps a not-yet-ordered material out of the inventory queue', function () {
    $this->actingAs($this->inventory);

    Livewire::test(ListMarketingMaterialFulfillmentsToReceive::class)
        ->assertCanNotSeeTableRecords([$this->physicalMaterial]);
});

it('moves a material out of the purchasing queue once ordered, and into the inventory queue', function () {
    $this->physicalMaterial->fulfillment()->create([
        'status' => MarketingMaterialFulfillmentStatus::Ordered,
        'vendor_name' => 'CV Percetakan Jaya',
        'order_date' => now(),
        'ordered_by' => $this->purchasing->id,
        'ordered_at' => now(),
    ]);

    $this->actingAs($this->purchasing);
    Livewire::test(ListMarketingMaterialFulfillments::class)
        ->assertCanNotSeeTableRecords([$this->physicalMaterial]);

    $this->actingAs($this->inventory);
    Livewire::test(ListMarketingMaterialFulfillmentsToReceive::class)
        ->assertCanSeeTableRecords([$this->physicalMaterial]);
});

it('removes a material from both queues once fully received', function () {
    $this->physicalMaterial->fulfillment()->create([
        'status' => MarketingMaterialFulfillmentStatus::Received,
        'vendor_name' => 'CV Percetakan Jaya',
        'ordered_by' => $this->purchasing->id,
        'ordered_at' => now(),
        'received_by' => $this->inventory->id,
        'received_at' => now(),
    ]);

    $this->actingAs($this->purchasing);
    Livewire::test(ListMarketingMaterialFulfillments::class)
        ->assertCanNotSeeTableRecords([$this->physicalMaterial]);

    $this->actingAs($this->inventory);
    Livewire::test(ListMarketingMaterialFulfillmentsToReceive::class)
        ->assertCanNotSeeTableRecords([$this->physicalMaterial]);
});

it('blocks a user without the matching process permission from the mutating actions', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo('view marketing material fulfillments');
    $this->actingAs($viewer);

    Livewire::test(ListMarketingMaterialFulfillments::class)
        ->assertTableActionHidden('mark_ordered', $this->physicalMaterial)
        ->assertTableActionHidden('mark_received', $this->physicalMaterial);
});

it('lets design staff upload a marketing material without full rnd project edit rights', function () {
    Storage::fake('b2');

    $this->actingAs($this->design);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->physicalMaterial->product->project->id,
        'product' => $this->physicalMaterial->product->id,
    ])
        ->call('openMaterialForm')
        ->set('materialType', 'sticker')
        ->set('materialTitle', 'Stiker Promo')
        ->set('materialFile', UploadedFile::fake()->create('sticker.pdf', 100, 'application/pdf'))
        ->call('saveMaterial')
        ->assertHasNoErrors();

    expect(RndProjectMarketingMaterial::where('title', 'Stiker Promo')->exists())->toBeTrue();
});

it('renders each role\'s own status-scoped marketing material page and its sidebar link', function () {
    $this->actingAs($this->purchasing);
    $this->get(route('filament.helpdesk.resources.marketing-material-fulfillments.index'))
        ->assertOk()
        ->assertSee('Perlu Dipesan')
        ->assertSee('Material Marketing (Perlu Dipesan)')
        ->assertSee('openGroups: ["purchasing"]', false)
        ->assertSee(route('filament.helpdesk.resources.marketing-material-fulfillments.index'), false);

    $this->actingAs($this->inventory);
    $this->get(route('filament.helpdesk.resources.marketing-material-fulfillments.to-receive'))
        ->assertOk()
        ->assertSee('Perlu Diterima')
        ->assertSee('Material Marketing (Perlu Diterima)')
        ->assertSee('openGroups: ["inventory"]', false)
        ->assertSee(route('filament.helpdesk.resources.marketing-material-fulfillments.to-receive'), false);
});
