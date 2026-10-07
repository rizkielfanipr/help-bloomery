<?php

use App\Filament\Helpdesk\Pages\ViewProjectProductPage;
use App\Filament\Helpdesk\Resources\Projects\Pages\ViewProject;
use App\Http\Controllers\Helpdesk\RndProductBomPdfController;
use App\Http\Controllers\Helpdesk\RndProjectBomPdfController;
use App\Models\Branch;
use App\Models\RndProductSalesProjection;
use App\Models\RndProject;
use App\Models\SalesRegion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
});

it('removes the legacy BOM PIN fields from users', function () {
    expect(Schema::hasColumn('users', 'use_bom_pin'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'bom_pin'))->toBeFalse();
});

it('shows only the BOM export action granted to the user', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo([
        'access backoffice',
        'view rnd projects',
        'view bill of materials',
        'export kitchen bill of materials',
    ]);
    $this->actingAs($user);

    $project = RndProject::query()->create([
        'name' => 'Scoped Export Project',
        'start_date' => '2026-09-01',
        'end_date' => '2026-10-31',
        'created_by' => $user->id,
    ]);
    $product = $project->products()->create([
        'name' => 'Scoped Export Product',
        'status' => 'development',
        'created_by' => $user->id,
    ]);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $project->id,
        'product' => $product->id,
    ])
        ->assertSee('Export Kitchen PDF')
        ->assertDontSee('Export Store PDF');

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->assertSee('Export Kitchen PDF')
        ->assertDontSee('Export Store PDF');

    $this->get(route('helpdesk.rnd-products.bom-pdf', [
        'project' => $project->id,
        'product' => $product->id,
        'scope' => 'store',
    ]))->assertForbidden();
});

it('keeps the complete styled product document when combining project PDFs', function () {
    $controller = app(RndProjectBomPdfController::class);
    $method = new ReflectionMethod($controller, 'combineRenderedDocuments');
    $html = $method->invoke($controller, collect([
        '<!DOCTYPE html><html><head><style>.kop{display:table}</style></head><body><div class="kop">First</div></body></html>',
        '<!DOCTYPE html><html><head><style>.ignored{color:red}</style></head><body><div class="kop">Second</div></body></html>',
    ]));

    expect($html)
        ->toStartWith('<!DOCTYPE html>')
        ->toContain('<style>.kop{display:table}</style>')
        ->toContain('<div class="kop">First</div>')
        ->toContain('page-break-before: always;')
        ->toContain('<div class="kop">Second</div>')
        ->not->toContain('.ignored{color:red}');
});

it('shows every regional sales channel price in the store PDF', function () {
    $product = (object) [
        'name' => 'Channel Product',
        'product_code' => 'CHANNEL-01',
        'description' => null,
    ];
    $price = (object) [
        'region' => (object) ['name' => 'Jakarta', 'code' => 'JKT'],
        'offline_price' => 30000,
        'online_price' => 35000,
        'dine_in_price' => 31000,
        'takeaway_price' => 32000,
        'gofood_price' => 36000,
        'grabfood_price' => 37000,
        'shopeefood_price' => 38000,
    ];

    $view = $this->view('exports.partials.rnd-bom-section', [
        'bom' => ['bomName' => 'Menu Product', 'bomDetails' => []],
        'bomModel' => (object) ['bom_name' => 'Menu Product', 'product_name' => 'Channel Product'],
        'exportScope' => 'store',
        'instruction' => null,
        'productPhoto' => null,
        'productRecord' => $product,
        'regionalPrices' => collect([$price]),
        'resultUnitMap' => [],
        'sectionLabel' => 'Menu',
    ]);

    $view->assertSeeTextInOrder([
        'Dine In', 'Rp 31.000',
        'Takeaway', 'Rp 32.000',
        'GoFood', 'Rp 36.000',
        'GrabFood', 'Rp 37.000',
        'ShopeeFood', 'Rp 38.000',
    ]);
});

it('renders project sales projections as a leading Store PDF section', function () {
    $project = RndProject::query()->create([
        'name' => 'Project Projection Store',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => auth()->id(),
    ]);
    $product = $project->products()->create([
        'name' => 'Salt Bread',
        'product_code' => 'SB-001',
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);
    $region = SalesRegion::query()->create([
        'name' => 'Yogyakarta',
        'code' => 'YOG',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $branch = Branch::factory()->create(['name' => 'Bloomery Kaliurang']);
    $projection = RndProductSalesProjection::factory()->create([
        'rnd_project_product_id' => $product->id,
        'sales_region_id' => $region->id,
        'projection_month' => '2026-10-01',
        'channel' => 'offline',
        'target_quantity' => 1250,
        'target_revenue' => 62500000,
        'target_outlets' => 4,
        'notes' => 'Prioritas pembukaan menu baru.',
        'created_by' => auth()->id(),
    ]);
    $projection->targetBranches()->attach($branch->id, ['target_quantity' => 750]);
    $product->load(['salesProjections.region', 'salesProjections.targetBranches']);

    $html = view('exports.partials.rnd-project-sales-projection', [
        'products' => collect([$product]),
    ])->render();

    expect($html)
        ->toContain('sales-projection-section')
        ->toContain('Sales Projection')
        ->toContain('Oct 2026')
        ->toContain('Yogyakarta')
        ->toContain('Offline')
        ->toContain('1.250,00')
        ->toContain('Rp 62.500.000')
        ->toContain('Bloomery Kaliurang: 750,00')
        ->not->toContain('Project Projection Store')
        ->not->toContain('Salt Bread · SB-001')
        ->not->toContain('Prioritas pembukaan menu baru.');
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
        ->assertSee('Centang resep di sebelah kiri. Preview PDF diperbarui otomatis sesuai pilihan.')
        ->assertSee('Preview Kitchen PDF')
        ->assertSeeHtml('lg:grid-cols-[minmax(19rem,24rem)_minmax(0,1fr)]')
        ->assertSeeHtml('touch-pan-y')
        ->assertSeeHtml('wire:model.live.debounce.400ms="exportBomIds"')
        ->assertDontSeeHtml('wire:model="exportBomComponentKeys')
        ->assertSet('exportBomIds', $boms->pluck('id')->all())
        ->assertSet('exportAutoBomKeys', [$selectedBom->id.':1980'])
        ->set('exportBomIds', [])
        ->assertHasErrors('exportBomIds')
        ->assertSet('pdfPreview', null)
        ->set('exportBomIds', [$selectedBom->id])
        ->assertHasNoErrors('exportBomIds')
        ->set('exportAutoBomKeys', [$selectedBom->id.':1980'])
        ->set('exportBomComponentKeys.'.$selectedBom->id, ['100'])
        ->assertNoRedirect()
        ->assertSet('pdfPreview.download_url', $exportUrl)
        ->assertSet('pdfPreview.preview_url', $exportUrl.(str_contains($exportUrl, '?') ? '&' : '?').'preview=1')
        ->assertSet('exportModalOpen', true);

    $projectExportUrl = route('helpdesk.rnd-projects.bom-pdf', [
        'project' => $project->id,
        'scope' => 'kitchen',
        'bom_ids' => (string) $selectedBom->id,
    ]);

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->call('openProjectBomExport', 'kitchen')
        ->assertSee('Pilih resep dari seluruh product. Preview PDF diperbarui otomatis sesuai checkbox.')
        ->assertSee('Preview Kitchen PDF')
        ->assertSeeHtml('wire:model.live.debounce.400ms="projectExportBomIds"')
        ->set('projectExportBomIds', [])
        ->assertHasErrors('projectExportBomIds')
        ->assertSet('pdfPreview', null)
        ->set('projectExportBomIds', [$selectedBom->id])
        ->assertHasNoErrors('projectExportBomIds')
        ->assertSet('pdfPreview.download_url', $projectExportUrl)
        ->assertSet('pdfPreview.preview_url', $projectExportUrl.(str_contains($projectExportUrl, '?') ? '&' : '?').'preview=1')
        ->assertSet('projectExportModalOpen', true);

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
        ->not->toContain('Sales Projection')
        ->not->toContain('Product Code / SKU')
        ->not->toContain('Product Detail');
});

it('exports all Store BOM products in a project as one permission-protected PDF', function () {
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
        ->assertHasNoErrors()
        ->assertSet('projectExportBomIds', $project->boms()->where('bom_type_name', 'Menu')->orderBy('id')->pluck('id')->all())
        ->assertNoRedirect()
        ->assertSet('pdfPreview.download_url', $exportUrl)
        ->assertSet('pdfPreview.preview_url', $exportUrl.(str_contains($exportUrl, '?') ? '&' : '?').'preview=1')
        ->assertSet('projectExportModalOpen', false);

    $this->get($exportUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=BOM-STORE-PROJECT-PROJECT-MULTI-PRODUCT.pdf');

    $this->get($exportUrl.'&preview=1')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=BOM-STORE-PROJECT-PROJECT-MULTI-PRODUCT.pdf');
});

it('serves the BOM PDF inline for the preview modal and as an attachment for download', function () {
    $html = view('filament.helpdesk.rnd-projects.partials.pdf-preview-modal', ['pdfPreview' => [
        'title' => 'Preview Store PDF', 'preview_url' => 'https://example.test/pdf?preview=1', 'download_url' => 'https://example.test/pdf',
    ]])->render();

    expect($html)->toContain('<iframe src="https://example.test/pdf?preview=1"')
        ->toContain('Download PDF')
        ->toContain('Buka di Tab Baru')
        ->toContain('role="dialog"');
});
