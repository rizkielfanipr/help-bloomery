<?php

use App\Actions\Technician\CreateServiceRequestAction;
use App\Enums\ServiceRequestStatus;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Storage::fake('b2');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->primary = Branch::factory()->create();
    $this->secondary = Branch::factory()->create();
    $this->requester = User::factory()->create(['is_active' => true]);
    $this->requester->syncBranchAccess([$this->primary->id, $this->secondary->id], $this->primary->id);
});

it('uses the primary branch for manual requests and leaves attachments optional', function () {
    $request = app(CreateServiceRequestAction::class)->execute($this->requester, 'Mesin tidak menyala', []);
    expect($request->branch_id)->toBe($this->primary->id)->and($request->source)->toBe('manual')
        ->and($request->scheduled_by)->toBe($this->requester->id)->and($request->asset_id)->toBeNull()
        ->and($request->scheduled_date)->toBeNull()->and($request->attachments)->toBeNull()
        ->and($request->technician_id)->toBeNull()->and($request->status)->toBe(ServiceRequestStatus::Submitted);
});

it('uses the scanned asset branch and retains validated attachment paths', function () {
    $asset = Asset::factory()->create(['branch_id' => $this->secondary->id]);
    $technician = User::factory()->create(['is_active' => true]);
    $technician->assignRole('TECHNICIAN');
    $technician->syncBranchAccess([$this->secondary->id], $this->secondary->id);
    $paths = ['service-request-attachments/photo.jpg'];
    $request = app(CreateServiceRequestAction::class)->execute($this->requester, 'Pendingin gagal', $paths, $asset);
    expect($request->branch_id)->toBe($this->secondary->id)->and($request->asset_id)->toBe($asset->id)
        ->and($request->source)->toBe('asset_qr')->and($request->attachments)->toBe($paths)
        ->and($request->technician_id)->toBe($technician->id)->and($request->assigned_at)->not->toBeNull();
    expect($technician->notifications()->count())->toBe(1)->and($asset->fresh()->status)->toBe('Maintenance');
});

it('rejects inactive or inaccessible assets and a manual requester without a branch', function () {
    $foreign = Branch::factory()->create();
    $assets = [Asset::factory()->create(['branch_id' => $foreign->id]), Asset::factory()->create(['branch_id' => $this->primary->id, 'is_active' => false])];
    foreach ($assets as $asset) {
        expect(fn () => app(CreateServiceRequestAction::class)->execute($this->requester, 'Kerusakan', [], $asset))->toThrow(HttpException::class);
    }
    $noBranch = User::factory()->create(['branch_id' => null]);
    expect(fn () => app(CreateServiceRequestAction::class)->execute($noBranch, 'Kerusakan', []))->toThrow(HttpException::class);
    expect(ServiceRequest::count())->toBe(0);
});
