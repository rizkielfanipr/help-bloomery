<?php

use App\Actions\Rnd\Bom\ReleaseBomToStoreSopAction;
use App\Filament\Helpdesk\Pages\ViewProjectProductPage;
use App\Filament\Helpdesk\Resources\Projects\Pages\ViewProject;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\RndProject;
use App\Models\StoreSop;
use App\Models\StoreSopCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    Storage::fake('b2');

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('SUPERADMIN');
    $this->actingAs($this->admin);

    $this->brand = Brand::factory()->create(['name' => 'Bloomery']);
    $this->branch = Branch::factory()->create([
        'brand_id' => $this->brand->id,
        'name' => 'Bloomery Central',
        'is_active' => true,
    ]);
    $this->category = StoreSopCategory::factory()->create([
        'name' => 'Produksi',
        'is_active' => true,
    ]);
    $this->project = RndProject::query()->create([
        'name' => 'Project SOP Baru',
        'start_date' => '2026-10-01',
        'end_date' => '2026-12-31',
        'created_by' => $this->admin->id,
    ]);
    $this->product = $this->project->products()->create([
        'name' => 'Whole Cake Blueberry',
        'product_code' => 'WCB-01',
        'status' => 'development',
        'created_by' => $this->admin->id,
    ]);
    $this->bom = $this->project->boms()->create([
        'esb_bom_id' => 901001,
        'bom_code' => 'BOM-WCB-01',
        'bom_name' => 'Whole Cake Blueberry Recipe',
        'product_name' => 'Whole Cake Blueberry',
        'uom_name' => 'pcs',
        'detail_snapshot' => [
            'bomID' => 901001,
            'bomCode' => 'BOM-WCB-01',
            'bomName' => 'Whole Cake Blueberry Recipe',
            'productName' => 'Whole Cake Blueberry',
            'uomName' => 'pcs',
            'bomDetails' => [[
                'productDetailID' => 901002,
                'productCode' => 'RM-CREAM',
                'productName' => 'Cream Cheese',
                'quantity' => 100,
                'uomName' => 'gram',
            ]],
        ],
        'created_by' => $this->admin->id,
    ]);
    $this->product->boms()->attach($this->bom->id, ['usage_type' => 'main']);
});

it('releases the selected R&D BOM PDF directly as a published Store SOP', function () {
    $result = app(ReleaseBomToStoreSopAction::class)->execute(
        user: $this->admin,
        project: $this->project,
        product: $this->product,
        scope: 'kitchen',
        bomIds: [$this->bom->id],
        autoBomKeys: [],
        componentKeys: [],
        metadata: [
            'code' => 'SOP-RND-001',
            'title' => 'SOP Whole Cake Blueberry',
            'store_sop_category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'branch_ids' => [$this->branch->id],
            'effective_date' => '2026-10-08',
            'expires_at' => '2027-10-08',
            'summary' => 'SOP produksi dari project R&D.',
        ],
    );

    $sop = $result['sop'];

    expect($result['created'])->toBeTrue()
        ->and($result['branch_count'])->toBe(1)
        ->and($sop->status)->toBe('published')
        ->and($sop->published_by)->toBe($this->admin->id)
        ->and($sop->source_rnd_project_id)->toBe($this->project->id)
        ->and($sop->source_rnd_project_product_id)->toBe($this->product->id)
        ->and($sop->source_scope)->toBe('kitchen')
        ->and($sop->source_release_key)->not->toBeNull()
        ->and($sop->branches()->pluck('branches.id')->all())->toBe([$this->branch->id]);

    Storage::disk('b2')->assertExists($sop->file_path);
});

it('does not duplicate an unchanged BOM release', function () {
    $metadata = [
        'code' => 'SOP-RND-FIRST',
        'title' => 'SOP Rilis Pertama',
        'store_sop_category_id' => $this->category->id,
        'brand_id' => $this->brand->id,
        'branch_ids' => [$this->branch->id],
        'effective_date' => '2026-10-08',
        'expires_at' => '2027-10-08',
        'summary' => null,
    ];
    $action = app(ReleaseBomToStoreSopAction::class);

    $first = $action->execute($this->admin, $this->project, $this->product, 'kitchen', [$this->bom->id], [], [], $metadata);
    $second = $action->execute($this->admin, $this->project, $this->product, 'kitchen', [$this->bom->id], [], [], [
        ...$metadata,
        'code' => 'SOP-RND-SECOND',
    ]);

    expect($first['created'])->toBeTrue()
        ->and($second['created'])->toBeFalse()
        ->and($second['sop']->is($first['sop']))->toBeTrue()
        ->and(StoreSop::query()->count())->toBe(1);
});

it('requires both BOM export and SOP publication permissions', function () {
    $limitedUser = User::factory()->create(['is_active' => true]);
    $limitedUser->givePermissionTo([
        'view bill of materials',
        'export kitchen bill of materials',
    ]);

    expect(fn () => app(ReleaseBomToStoreSopAction::class)->execute(
        $limitedUser,
        $this->project,
        $this->product,
        'kitchen',
        [$this->bom->id],
        [],
        [],
        [],
    ))->toThrow(AuthorizationException::class);
});

it('shows the release action and simple Store SOP form in the product PDF preview', function () {
    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])
        ->call('openExportPdf', 'kitchen')
        ->assertSee('Rilis SOP')
        ->call('openReleaseSopModal')
        ->assertSet('releaseSopModalOpen', true)
        ->assertSee('Rilis ke SOP Store')
        ->assertSee('Nomor SOP')
        ->assertSee('Target Branch')
        ->assertSee('Rilis & Publikasikan');

    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->call('openProjectBomExport', 'kitchen')
        ->assertSee('Rilis SOP')
        ->call('openReleaseSopModal')
        ->assertSet('releaseSopModalOpen', true)
        ->assertSee('Rilis ke SOP Store');
});
