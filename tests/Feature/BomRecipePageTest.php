<?php

use App\Filament\Helpdesk\Pages\CreateBomRecipePage;
use App\Filament\Helpdesk\Pages\EditBomRecipePage;
use App\Filament\Helpdesk\Pages\ViewBomPage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
});

it('renders the BOM recipe form without a Blade parse error', function () {
    Livewire::test(CreateBomRecipePage::class)
        ->assertSee('Buat Bill of Material Baru')
        ->assertSee('Pilih produk hasil');
});

it('renders the BOM view and update workspaces', function () {
    config()->set([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token'],
        ]),
        'https://core-esb.test/product/bom/42' => Http::response([
            'status' => 'ok',
            'result' => bomDetail(),
        ]),
    ]);

    Livewire::test(ViewBomPage::class, ['bom' => 42])
        ->assertSee('Croissant Assembly')
        ->assertSee('Butter');

    Livewire::test(EditBomRecipePage::class, ['bom' => 42])
        ->assertSet('isEditing', true)
        ->assertSet('data.bomName', 'Croissant Assembly')
        ->assertSee('Update Bill of Material');
});

function bomDetail(): array
{
    return [
        'bomID' => 42,
        'bomTypeID' => 1,
        'bomTypeName' => 'Assembly',
        'bomName' => 'Croissant Assembly',
        'bomCode' => 'BOM-CRS',
        'productDetailID' => 100,
        'productName' => 'Croissant',
        'productCode' => 'CRS',
        'uomName' => 'PCS',
        'bomCostTotal' => 0,
        'notes' => 'Test BOM',
        'accessType' => 0,
        'bomDetails' => [[
            'ID' => 7,
            'productID' => 2,
            'productDetailID' => 200,
            'productName' => 'Butter',
            'productCode' => 'BTR',
            'uomName' => 'GRAM',
            'qty' => 100,
            'lastHpp' => 125,
            'yieldPercent' => 2,
            'printGroup' => '',
        ]],
    ];
}
