<?php

use App\Filament\Helpdesk\Pages\SalesProjectionPage;
use App\Models\Branch;
use App\Models\RndProductSalesProjection;
use App\Models\RndProject;
use App\Models\RndProjectProduct;
use App\Models\SalesRegion;
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

it('shows submitted product projections and their branch targets', function () {
    $project = RndProject::query()->create([
        'name' => 'Growth Project',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => auth()->id(),
    ]);
    $product = RndProjectProduct::query()->create([
        'rnd_project_id' => $project->id,
        'name' => 'Strawberry Matcha',
        'product_code' => 'SKU-SM-01',
        'status' => 'ready',
        'created_by' => auth()->id(),
    ]);
    $region = SalesRegion::query()->where('is_active', true)->firstOrFail();
    $branch = Branch::factory()->create(['name' => 'Bloomery Pabelan', 'is_active' => true]);
    $projection = RndProductSalesProjection::query()->create([
        'rnd_project_product_id' => $product->id,
        'sales_region_id' => $region->id,
        'projection_month' => '2026-10-01',
        'channel' => 'offline',
        'target_quantity' => 500,
        'target_revenue' => 25000000,
        'target_outlets' => 1,
        'notes' => 'Target launching Oktober.',
        'created_by' => auth()->id(),
    ]);
    $projection->targetBranches()->attach($branch->id, ['target_quantity' => 500]);

    Livewire::test(SalesProjectionPage::class)
        ->assertSee('Strawberry Matcha')
        ->assertSee('SKU-SM-01')
        ->assertSee('Bloomery Pabelan')
        ->assertSee('Target launching Oktober.')
        ->assertSee('25.000.000')
        ->set('branchId', (string) $branch->id)
        ->assertSee('Strawberry Matcha');
});

it('registers the sales and growth menu on the custom sidebar', function () {
    $this->get(SalesProjectionPage::getUrl())
        ->assertOk()
        ->assertSee('Sales &amp; Growth', false)
        ->assertSee('Sales Projection');
});
