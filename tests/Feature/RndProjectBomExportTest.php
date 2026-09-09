<?php

use App\Filament\Helpdesk\Pages\ViewProjectProductPage;
use App\Filament\Helpdesk\Resources\Projects\Pages\ViewProject;
use App\Http\Controllers\Helpdesk\RndProductBomPdfController;
use App\Models\RndProject;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $admin = User::factory()->create(['is_active' => true, 'use_bom_pin' => true, 'bom_pin' => Hash::make('246810')]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
    config()->set('rnd.bom_pin', '246810');
});

it('exports only the selected BOM from the export checklist', function () {
    $project = RndProject::query()->create([
        'name' => 'Selective SOP Export',
        'start_date' => '2026-09-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $product = $project->products()->create([
        'name' => 'Selective Product',
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);
    $boms = collect(['Ditampilkan', 'Disembunyikan'])->map(function (string $name, int $index) use ($project, $product) {
        $bom = $project->boms()->create([
            'esb_bom_id' => 980 + $index,
            'bom_code' => 'SOP-'.($index + 1),
            'bom_name' => $name,
            'detail_snapshot' => [
                'bomID' => 980 + $index,
                'bomName' => $name,
                'bomDetails' => [
                    ['productDetailID' => 100 + ($index * 10), 'productCode' => 'CMP-A-'.$index, 'productName' => 'Component A'],
                    ['productDetailID' => 101 + ($index * 10), 'productCode' => 'CMP-B-'.$index, 'productName' => 'Component B'],
                ],
            ],
            'created_by' => auth()->id(),
        ]);
        $product->boms()->attach($bom->id, ['usage_type' => 'main']);

        return $bom;
    });
    $selectedBom = $boms->first();
    $selectedBom->documentMaterials()->create([
        'name' => 'Air',
        'quantity' => 200,
        'unit' => 'ml',
        'notes' => 'Untuk melarutkan bahan',
        'sort_order' => 0,
    ]);
    $exportUrl = route('helpdesk.rnd-products.bom-pdf', [
        'project' => $project->id,
        'product' => $product->id,
        'scope' => 'kitchen',
        'bom_ids' => (string) $selectedBom->id,
    ]);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $project->id,
        'product' => $product->id,
    ])
        ->set('autoWipComponentRecipes', [
            $selectedBom->id => [[
                'bomID' => 1980,
                'bomCode' => 'BOM-FILLING',
                'bomName' => 'Blueberry Cheesecake Filling',
                'productName' => 'Blueberry Cheesecake Filling',
                'productCode' => 'BW-FILLING',
                'uomName' => 'GR',
                'sourceQty' => 1,
                'sourceUnit' => 'GR',
                'bomDetails' => [],
            ]],
        ])
        ->call('openExportPdf', 'kitchen')
        ->assertSee('Main Recipe')
        ->assertSee('Blueberry Cheesecake Filling')
        ->assertSee('Component')
        ->assertSeeHtml('h-[calc(100dvh-2rem)]')
        ->assertSeeHtml('max-h-[42rem]')
        ->assertSeeHtml('touch-pan-y')
        ->assertDontSeeHtml('wire:model="exportBomComponentKeys')
        ->assertSet('exportBomIds', $boms->pluck('id')->all())
        ->assertSet('exportAutoBomKeys', [$selectedBom->id.':1980'])
        ->set('exportBomIds', [$selectedBom->id])
        ->set('exportAutoBomKeys', [$selectedBom->id.':1980'])
        ->set('exportBomComponentKeys.'.$selectedBom->id, ['100'])
        ->set('exportPin', '246810')
        ->call('exportBomPdf')
        ->assertRedirect($exportUrl);

    expect(session(RndProductBomPdfController::autoBomSessionKey(auth()->id(), $project->id, $product->id)))
        ->toBe([$selectedBom->id.':1980']);

    $exportProduct = $project->products()->with(['boms', 'currentRegionalPrices.region'])->findOrFail($product->id);
    $data = app(RndProductBomPdfController::class)->buildExportData(
        $project,
        $exportProduct,
        'kitchen',
        [$selectedBom->id],
        [$selectedBom->id => ['100']],
    );

    expect($data['exportBoms']->pluck('id')->all())->toBe([$selectedBom->id])
        ->and($data['details'][$selectedBom->id]['bomDetails'])->toHaveCount(1)
        ->and($data['details'][$selectedBom->id]['bomDetails'][0]['productCode'])->toBe('CMP-A-0');

    $dataWithDocumentMaterial = app(RndProductBomPdfController::class)->buildExportData(
        $project,
        $exportProduct,
        'kitchen',
        [$selectedBom->id],
    );

    expect($dataWithDocumentMaterial['details'][$selectedBom->id]['bomDetails'])->toHaveCount(3)
        ->and($dataWithDocumentMaterial['details'][$selectedBom->id]['bomDetails'][2])->toMatchArray([
            'productName' => 'Air',
            'uomName' => 'ml',
            'qty' => 200.0,
            'isDocumentOnly' => true,
        ]);
});

it('renders a selected main BOM without indexing an unselected child BOM', function () {
    $project = RndProject::query()->create([
        'name' => 'Partial Hierarchy Export',
        'start_date' => '2026-09-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $product = $project->products()->create([
        'name' => 'Ayam Woku',
        'product_code' => 'ATL',
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);
    $main = $project->boms()->create([
        'esb_bom_id' => 991,
        'bom_code' => 'ATL',
        'bom_name' => 'ATL | Ayam Woku',
        'detail_snapshot' => ['bomID' => 991, 'bomName' => 'ATL | Ayam Woku', 'bomDetails' => []],
        'created_by' => auth()->id(),
    ]);
    $child = $project->boms()->create([
        'esb_bom_id' => 992,
        'bom_code' => 'BW984',
        'bom_name' => 'ATL | Bumbu Woku',
        'detail_snapshot' => ['bomID' => 992, 'bomName' => 'ATL | Bumbu Woku', 'bomDetails' => []],
        'created_by' => auth()->id(),
    ]);
    $product->boms()->attach($main->id, ['usage_type' => 'main']);
    $product->boms()->attach($child->id, ['usage_type' => 'component', 'parent_rnd_project_bom_id' => $main->id]);

    $exportProduct = $project->products()->with(['boms', 'currentRegionalPrices.region'])->findOrFail($product->id);
    $data = app(RndProductBomPdfController::class)->buildExportData($project, $exportProduct, 'kitchen', [$main->id]);
    $html = view('exports.rnd-product-bom-pdf', $data)->render();

    expect($html)
        ->toContain('ATL | Ayam Woku')
        ->not->toContain('ATL | Bumbu Woku')
        ->not->toContain('Product Code / SKU')
        ->not->toContain('Product Detail');
});

it('exports all Store BOM products in a project as one PIN-protected PDF', function () {
    $project = RndProject::query()->create([
        'name' => 'Project Multi Product',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);

    foreach ([1 => 'Salt Bread', 2 => 'Belgian Cake'] as $index => $name) {
        $product = $project->products()->create([
            'name' => $name,
            'product_code' => 'PRD-00'.$index,
            'status' => 'development',
            'created_by' => auth()->id(),
        ]);
        $bom = $project->boms()->create([
            'esb_bom_id' => 950 + $index,
            'bom_code' => 'MENU-00'.$index,
            'bom_name' => $name.' Menu',
            'bom_type_name' => 'Menu',
            'detail_snapshot' => [
                'bomID' => 950 + $index,
                'bomName' => $name.' Menu',
                'bomCode' => 'MENU-00'.$index,
                'bomTypeName' => 'Menu',
                'bomDetails' => [[
                    'productCode' => 'ITEM-00'.$index,
                    'productName' => $name,
                    'uomName' => 'PCS',
                    'qty' => 1,
                ]],
            ],
            'created_by' => auth()->id(),
        ]);
        $product->boms()->attach($bom->id, ['usage_type' => 'menu']);
    }

    $exportUrl = route('helpdesk.rnd-projects.bom-pdf', ['project' => $project->id, 'scope' => 'store']);

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->assertSee('Export Kitchen PDF')
        ->assertSee('Export Store PDF')
        ->call('openProjectBomExport', 'store')
        ->assertSee('Menu')
        ->assertSeeHtml('h-[calc(100dvh-2rem)]')
        ->assertSeeHtml('max-h-[42rem]')
        ->assertSeeHtml('touch-pan-y')
        ->assertDontSeeHtml('wire:model="projectExportBomComponentKeys')
        ->set('projectExportPin', '246810')
        ->call('exportProjectBomPdf')
        ->assertHasNoErrors()
        ->assertRedirect($exportUrl);

    $this->get($exportUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=BOM-STORE-PROJECT-PROJECT-MULTI-PRODUCT.pdf');
});
