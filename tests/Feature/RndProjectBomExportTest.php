<?php

use App\Filament\Helpdesk\Resources\Projects\Pages\ViewProject;
use App\Models\RndProject;
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
    config()->set('rnd.bom_pin', '246810');
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
        ->set('projectExportPin', '246810')
        ->call('exportProjectBomPdf')
        ->assertHasNoErrors()
        ->assertRedirect($exportUrl);

    $this->get($exportUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=BOM-STORE-PROJECT-PROJECT-MULTI-PRODUCT.pdf');
});
