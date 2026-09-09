<?php

use App\Enums\ServiceRequestStatus;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('b2');
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('creates an asset service request with required photos and assigns the least busy branch technician', function () {
    $branch = Branch::factory()->create();
    $asset = Asset::factory()->create(['branch_id' => $branch->id]);
    $reporter = User::factory()->create(['is_active' => true]);
    $reporter->syncBranchAccess([$branch->id], $branch->id);
    $busyTechnician = User::factory()->create(['is_active' => true]);
    $busyTechnician->assignRole('TECHNICIAN');
    $busyTechnician->syncBranchAccess([$branch->id], $branch->id);
    $availableTechnician = User::factory()->create(['is_active' => true]);
    $availableTechnician->assignRole('TECHNICIAN');
    $availableTechnician->syncBranchAccess([$branch->id], $branch->id);
    ServiceRequest::factory()->create([
        'branch_id' => $branch->id,
        'technician_id' => $busyTechnician->id,
        'scheduled_by' => $reporter->id,
        'status' => ServiceRequestStatus::InProgress,
    ]);

    $this->actingAs($reporter)->post(route('assets.report', $asset->qr_token), [
        'issue' => 'Mesin tidak dapat dinyalakan.',
        'photos' => [UploadedFile::fake()->image('kendala.jpg')],
    ])->assertRedirect(route('assets.scan', $asset->qr_token));

    $request = ServiceRequest::query()->where('asset_id', $asset->id)->sole();
    expect($request->source)->toBe('asset_qr')
        ->and($request->branch_id)->toBe($branch->id)
        ->and($request->scheduled_by)->toBe($reporter->id)
        ->and($request->technician_id)->toBe($availableTechnician->id)
        ->and($request->assigned_at)->not->toBeNull()
        ->and($request->attachments)->toHaveCount(1);
    Storage::disk('b2')->assertExists($request->attachments[0]);
});

it('requires an authenticated branch user issue and at least one image', function () {
    $branch = Branch::factory()->create();
    $asset = Asset::factory()->create(['branch_id' => $branch->id]);
    $reporter = User::factory()->create(['is_active' => true]);
    $reporter->syncBranchAccess([$branch->id], $branch->id);

    $this->post(route('assets.report', $asset->qr_token))->assertRedirect(route('login'));

    $this->actingAs($reporter)
        ->from(route('assets.scan', $asset->qr_token))
        ->post(route('assets.report', $asset->qr_token), ['issue' => 'Rusak'])
        ->assertRedirect(route('assets.scan', $asset->qr_token))
        ->assertSessionHasErrors('photos');
});

it('returns a guest to the scanned asset after login', function () {
    $asset = Asset::factory()->create();

    $this->get(route('assets.login', $asset->qr_token))->assertRedirect();

    expect(session('url.intended'))->toBe(route('assets.scan', $asset->qr_token));
});

it('leaves the request unassigned when the asset branch has no technician', function () {
    $branch = Branch::factory()->create();
    $asset = Asset::factory()->create(['branch_id' => $branch->id]);
    $reporter = User::factory()->create(['is_active' => true]);
    $reporter->syncBranchAccess([$branch->id], $branch->id);

    $this->actingAs($reporter)->post(route('assets.report', $asset->qr_token), [
        'issue' => 'Chiller tidak dingin.',
        'photos' => [UploadedFile::fake()->image('chiller.jpg')],
    ])->assertRedirect();

    expect(ServiceRequest::query()->where('asset_id', $asset->id)->sole()->technician_id)->toBeNull();
});
