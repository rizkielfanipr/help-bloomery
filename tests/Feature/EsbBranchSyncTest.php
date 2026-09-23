<?php

use App\Filament\Helpdesk\Resources\Branches\Pages\ListBranches;
use App\Models\Branch;
use App\Models\User;
use App\Services\EsbBranchSyncService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'blss-user', 'password' => 'secret']);
    Cache::flush();
});

it('synchronizes numeric branch IDs by company code and branch code', function () {
    $branchA = Branch::factory()->create(['name' => 'Bloomery A']);
    $branchB = Branch::factory()->create(['name' => 'Bloomery B']);
    $mappingA = $branchA->esbCodes()->create(['esb_branch_code' => 'BLA', 'esb_comcode' => 'BLSS', 'is_active' => true]);
    $mappingB = $branchB->esbCodes()->create(['esb_branch_code' => 'BLB', 'esb_comcode' => 'BLSS', 'is_active' => true]);

    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => [
            ['branchID' => 17, 'branchCode' => 'BLA', 'branchName' => 'Bloomery A'],
            ['branchID' => 18, 'branchCode' => 'blb', 'branchName' => 'Bloomery B'],
        ]]),
    ]);

    $result = app(EsbBranchSyncService::class)->syncAll();

    expect($result)->toMatchArray(['synced' => 2, 'unchanged' => 0, 'missing' => 0, 'ambiguous' => 0, 'failed' => 0])
        ->and($mappingA->fresh()->esb_branch_id)->toBe(17)
        ->and($mappingB->fresh()->esb_branch_id)->toBe(18)
        ->and($mappingA->fresh()->esb_synced_at)->not->toBeNull();

    Http::assertSentCount(2);
});

it('does not overwrite mappings when the ESB result is missing ambiguous or invalid', function () {
    $branch = Branch::factory()->create(['name' => 'Bloomery Test']);
    $missing = $branch->esbCodes()->create(['esb_branch_id' => 41, 'esb_branch_code' => 'MISS', 'esb_comcode' => 'BLSS', 'is_active' => true]);
    $ambiguous = $branch->esbCodes()->create(['esb_branch_id' => 42, 'esb_branch_code' => 'DUP', 'esb_comcode' => 'BLSS', 'is_active' => true]);
    $invalid = $branch->esbCodes()->create(['esb_branch_id' => 43, 'esb_branch_code' => 'BAD', 'esb_comcode' => 'BLSS', 'is_active' => true]);

    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => [
            ['branchID' => 50, 'branchCode' => 'DUP'],
            ['branchID' => 51, 'branchCode' => 'dup'],
            ['branchID' => null, 'branchCode' => 'BAD'],
        ]]),
    ]);

    $result = app(EsbBranchSyncService::class)->syncAll();

    expect($result)->toMatchArray(['synced' => 0, 'unchanged' => 0, 'missing' => 1, 'ambiguous' => 1, 'failed' => 1])
        ->and($missing->fresh()->esb_branch_id)->toBe(41)
        ->and($ambiguous->fresh()->esb_branch_id)->toBe(42)
        ->and($invalid->fresh()->esb_branch_id)->toBe(43)
        ->and($result['details'])->toHaveCount(3);
});

it('isolates a failed company while synchronizing other company codes', function () {
    config()->set('esb.core.companies.BLO6', ['username' => null, 'password' => null]);
    $branch = Branch::factory()->create();
    $successful = $branch->esbCodes()->create(['esb_branch_code' => 'BLA', 'esb_comcode' => 'BLSS', 'is_active' => true]);
    $failed = $branch->esbCodes()->create(['esb_branch_code' => 'BL6', 'esb_comcode' => 'BLO6', 'is_active' => true]);

    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => [
            ['branchID' => 17, 'branchCode' => 'BLA'],
        ]]),
    ]);

    $result = app(EsbBranchSyncService::class)->syncAll();

    expect($result)->toMatchArray(['synced' => 1, 'failed' => 1])
        ->and($successful->fresh()->esb_branch_id)->toBe(17)
        ->and($failed->fresh()->esb_branch_id)->toBeNull();
});

it('exposes the sync action to branch editors and displays its result', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view branches', 'edit branches']);
    $this->actingAs($user);

    $service = Mockery::mock(EsbBranchSyncService::class);
    $service->shouldReceive('syncAll')->once()->andReturn([
        'synced' => 1, 'unchanged' => 2, 'missing' => 0, 'ambiguous' => 0, 'failed' => 0, 'details' => [],
    ]);
    app()->instance(EsbBranchSyncService::class, $service);

    Livewire::test(ListBranches::class)
        ->assertActionVisible('syncEsbBranches')
        ->callAction('syncEsbBranches')
        ->assertNotified('Sync Branch ESB berhasil');
});
