<?php

use App\Filament\Helpdesk\Resources\Assets\Pages\ListAssets;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\User;
use App\Observers\AssetObserver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('b2');
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('generates a stable asset number token and qr code', function () {
    $branch = Branch::factory()->create();
    $asset = Asset::factory()->create(['branch_id' => $branch->id]);

    expect($asset->asset_number)->toBe(sprintf('AST-BR%04d-%s-%06d', $branch->id, now()->format('Y'), $asset->id))
        ->and($asset->qr_token)->not->toBeEmpty()
        ->and($asset->qr_svg_path)->toBe("assets/{$branch->id}/{$asset->id}.svg");
    Storage::disk('b2')->assertExists($asset->qr_svg_path);

    $token = $asset->qr_token;
    $asset->update(['name' => 'Mesin Espresso Utama']);
    app(AssetObserver::class)->regenerate($asset);

    expect($asset->fresh()->qr_token)->toBe($token);
    Storage::disk('b2')->assertExists($asset->qr_svg_path);
});

it('shows asset qr menu and restricts records by branch access', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $allowedBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $technician = User::factory()->create(['is_active' => true]);
    $technician->assignRole('TECHNICIAN');
    $technician->syncBranchAccess([$allowedBranch->id], $allowedBranch->id);
    $visibleAsset = Asset::factory()->create(['branch_id' => $allowedBranch->id, 'name' => 'Asset Terlihat']);
    Asset::factory()->create(['branch_id' => $otherBranch->id, 'name' => 'Asset Tersembunyi']);
    $this->actingAs($technician);

    $this->get(route('filament.helpdesk.resources.assets.index'))
        ->assertOk()
        ->assertSee('Asset QR')
        ->assertSee('openGroups: ["technician"]', false)
        ->assertSee(route('filament.helpdesk.resources.assets.index'), false);

    Livewire::test(ListAssets::class)
        ->assertCanSeeTableRecords([$visibleAsset])
        ->assertCountTableRecords(1);
});

it('serves the qr scan identity and protects label downloads by branch', function () {
    $branch = Branch::factory()->create();
    $asset = Asset::factory()->create(['branch_id' => $branch->id]);
    $technician = User::factory()->create(['is_active' => true]);
    $technician->assignRole('TECHNICIAN');
    $technician->syncBranchAccess([$branch->id], $branch->id);

    $this->get(route('assets.scan', $asset->qr_token))
        ->assertOk()
        ->assertSee($asset->asset_number)
        ->assertSee($asset->name);

    $this->actingAs($technician)
        ->get(route('helpdesk.assets.qr', $asset))
        ->assertOk()
        ->assertDownload("qr-{$asset->asset_number}.svg");

    $this->get(route('helpdesk.assets.label-pdf', $asset))
        ->assertOk()
        ->assertDownload("label-asset-{$asset->asset_number}.pdf");

    $this->get(route('helpdesk.assets.labels-pdf', ['ids' => [$asset->id]]))
        ->assertOk()
        ->assertDownload('label-assets.pdf');
});
