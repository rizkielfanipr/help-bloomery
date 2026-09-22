<?php

use App\Filament\Helpdesk\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\User;
use App\Services\EsbBranchMappingResolver;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('resolves an ESB branch ID to its local branch within the company code', function () {
    $branch = Branch::factory()->create();
    $mapping = $branch->esbCodes()->create([
        'esb_branch_id' => 373, 'esb_branch_code' => 'BLA', 'esb_comcode' => 'BLSS', 'is_active' => true,
    ]);

    $resolved = app(EsbBranchMappingResolver::class)->resolve('BLSS', 373);

    expect($resolved?->is($mapping))->toBeTrue()
        ->and($resolved?->branch_id)->toBe($branch->id);
});

it('learns the numeric ESB branch ID from an existing company and branch code mapping', function () {
    $mapping = Branch::factory()->create()->esbCodes()->create([
        'esb_branch_id' => null, 'esb_branch_code' => 'BLA', 'esb_comcode' => 'BLSS', 'is_active' => true,
    ]);

    $resolved = app(EsbBranchMappingResolver::class)->resolve('BLSS', 373, 'BLA');

    expect($resolved?->is($mapping))->toBeTrue()
        ->and($mapping->fresh()->esb_branch_id)->toBe(373);
});

it('does not match the same numeric branch ID from another company code', function () {
    Branch::factory()->create()->esbCodes()->create([
        'esb_branch_id' => 373, 'esb_branch_code' => 'BLA', 'esb_comcode' => 'OTHER', 'is_active' => true,
    ]);

    expect(app(EsbBranchMappingResolver::class)->resolve('BLSS', 373))->toBeNull();
});

it('scopes Receiving records with local branch IDs rather than ESB branch IDs', function () {
    $allowedBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $viewer = User::factory()->create(['branch_id' => $allowedBranch->id, 'access_all_branches' => false, 'is_active' => true]);
    $viewer->givePermissionTo('view goods receipts');
    $this->actingAs($viewer);

    $visible = GoodsReceipt::factory()->create(['local_branch_id' => $allowedBranch->id, 'esb_branch_id' => $otherBranch->id]);
    $hidden = GoodsReceipt::factory()->create(['local_branch_id' => $otherBranch->id, 'esb_branch_id' => $allowedBranch->id]);

    expect(GoodsReceiptResource::getEloquentQuery()->pluck('id')->all())->toBe([$visible->id])
        ->and($viewer->can('view', $visible))->toBeTrue()
        ->and($viewer->can('view', $hidden))->toBeFalse();
});

it('allows global branch access to include historical unmapped receipts', function () {
    $viewer = User::factory()->create(['access_all_branches' => true, 'is_active' => true]);
    $viewer->givePermissionTo('view goods receipts');
    $this->actingAs($viewer);

    $mapped = GoodsReceipt::factory()->create();
    $historical = GoodsReceipt::factory()->create(['local_branch_id' => null]);

    expect(GoodsReceiptResource::getEloquentQuery()->pluck('id')->all())->toContain($mapped->id, $historical->id)
        ->and($viewer->can('view', $historical))->toBeTrue();
});

it('uses master mapping to authorize historical receipts without a local branch ID', function () {
    $branch = Branch::factory()->create();
    $branch->esbCodes()->create([
        'esb_branch_id' => 373, 'esb_branch_code' => 'BLA', 'esb_comcode' => 'BLSS', 'is_active' => true,
    ]);
    $viewer = User::factory()->create(['branch_id' => $branch->id, 'access_all_branches' => false, 'is_active' => true]);
    $viewer->givePermissionTo('view goods receipts');
    $this->actingAs($viewer);
    $historical = GoodsReceipt::factory()->create([
        'company_code' => 'BLSS', 'esb_branch_id' => 373, 'local_branch_id' => null,
    ]);

    expect(GoodsReceiptResource::getEloquentQuery()->pluck('id')->all())->toBe([$historical->id])
        ->and($viewer->can('view', $historical))->toBeTrue();
});
