<?php

use App\Filament\Helpdesk\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Models\Branch;
use App\Models\BranchEsbCode;
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

it('looks up an exact active mapping before a branch code match without changing either', function () {
    $exact = Branch::factory()->create()->esbCodes()->create([
        'esb_branch_id' => 373, 'esb_branch_code' => 'OLD', 'esb_comcode' => 'BLSS', 'is_active' => true,
    ]);
    Branch::factory()->create()->esbCodes()->create([
        'esb_branch_id' => 374, 'esb_branch_code' => 'BLA', 'esb_comcode' => 'BLSS', 'is_active' => true,
    ]);
    $before = BranchEsbCode::query()->orderBy('id')->get()->map->getAttributes()->all();

    $found = app(EsbBranchMappingResolver::class)->lookup('BLSS', 373, 'BLA');

    expect($found?->is($exact))->toBeTrue()
        ->and(BranchEsbCode::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($before);
});

it('looks up a single active branch code match without learning its ESB branch ID', function () {
    $mapping = Branch::factory()->create()->esbCodes()->create([
        'esb_branch_id' => null, 'esb_branch_code' => 'BLA', 'esb_comcode' => 'BLSS', 'is_active' => true,
    ]);

    $found = app(EsbBranchMappingResolver::class)->lookup('BLSS', 373, 'BLA');

    expect($found?->is($mapping))->toBeTrue()
        ->and($mapping->fresh()->esb_branch_id)->toBeNull();
});

it('treats inactive, other company, missing, and ambiguous mappings as unavailable metadata', function () {
    Branch::factory()->create()->esbCodes()->create([
        'esb_branch_id' => 373, 'esb_branch_code' => 'INA', 'esb_comcode' => 'BLSS', 'is_active' => false,
    ]);
    Branch::factory()->create()->esbCodes()->create([
        'esb_branch_id' => 373, 'esb_branch_code' => 'SPN', 'esb_comcode' => 'SPN', 'is_active' => true,
    ]);
    Branch::factory()->create()->esbCodes()->create([
        'esb_branch_id' => 380, 'esb_branch_code' => 'DUP', 'esb_comcode' => 'BLSS', 'is_active' => true,
    ]);
    Branch::factory()->create()->esbCodes()->create([
        'esb_branch_id' => 381, 'esb_branch_code' => 'DUP', 'esb_comcode' => 'BLSS', 'is_active' => true,
    ]);
    $before = BranchEsbCode::query()->orderBy('id')->get()->map->getAttributes()->all();
    $resolver = app(EsbBranchMappingResolver::class);

    expect($resolver->lookup('BLSS', 373))->toBeNull()
        ->and($resolver->lookup('BLSS', 373, 'INA'))->toBeNull()
        ->and($resolver->lookup('BLSS', 999, 'MISSING'))->toBeNull()
        ->and($resolver->lookup('BLSS', 999, 'DUP'))->toBeNull()
        ->and($resolver->lookup('BLSS', 999, ''))->toBeNull()
        ->and(BranchEsbCode::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($before);
});

it('keeps unmapped central Receiving records out of a limited viewer scope', function () {
    $branch = Branch::factory()->create();
    $viewer = User::factory()->create(['branch_id' => $branch->id, 'access_all_branches' => false, 'is_active' => true]);
    $viewer->givePermissionTo('view goods receipts');
    $this->actingAs($viewer);
    $unmapped = GoodsReceipt::factory()->create(['company_code' => 'BLSS', 'esb_branch_id' => 999, 'local_branch_id' => null]);

    expect(GoodsReceiptResource::getEloquentQuery()->pluck('id')->all())->not->toContain($unmapped->id)
        ->and($viewer->can('view', $unmapped))->toBeFalse();
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
