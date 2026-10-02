<?php

use App\Models\Branch;
use App\Models\StoreSalesOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lets only users with the matching permission view any, create, update, update status, and delete orders', function () {
    $operator = User::factory()->create(['is_active' => true]);
    $operator->givePermissionTo([
        'view any store sales orders', 'view store sales orders',
        'create store sales orders', 'update store sales orders',
        'update store sales order status', 'delete store sales orders',
    ]);
    $outsider = User::factory()->create(['is_active' => true]);
    $branch = Branch::factory()->create();
    $operator->branch_id = $branch->id;
    $operator->save();
    $order = StoreSalesOrder::factory()->create(['branch_id' => $branch->id]);

    expect($operator->can('viewAny', StoreSalesOrder::class))->toBeTrue()
        ->and($operator->can('view', $order))->toBeTrue()
        ->and($operator->can('create', StoreSalesOrder::class))->toBeTrue()
        ->and($operator->can('update', $order))->toBeTrue()
        ->and($operator->can('updateStatus', $order))->toBeTrue()
        ->and($operator->can('delete', $order))->toBeTrue()
        ->and($outsider->can('viewAny', StoreSalesOrder::class))->toBeFalse()
        ->and($outsider->can('view', $order))->toBeFalse()
        ->and($outsider->can('create', StoreSalesOrder::class))->toBeFalse()
        ->and($outsider->can('update', $order))->toBeFalse()
        ->and($outsider->can('updateStatus', $order))->toBeFalse()
        ->and($outsider->can('delete', $order))->toBeFalse();
});

it('always lets the submitter view their own order, even without the view permission or branch access', function () {
    $otherBranch = Branch::factory()->create();
    $submitter = User::factory()->create(['is_active' => true, 'branch_id' => $otherBranch->id]);
    $order = StoreSalesOrder::factory()->create([
        'submitted_by' => $submitter->id,
        'branch_id' => Branch::factory()->create()->id,
    ]);

    expect($submitter->can('view', $order))->toBeTrue();
});

it('restricts a reviewer to orders within their accessible branches', function () {
    $accessibleBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $accessibleBranch->id]);
    $reviewer->givePermissionTo(['view any store sales orders', 'view store sales orders', 'update store sales orders', 'update store sales order status']);

    $ownBranchOrder = StoreSalesOrder::factory()->create(['branch_id' => $accessibleBranch->id]);
    $otherBranchOrder = StoreSalesOrder::factory()->create(['branch_id' => $otherBranch->id]);

    expect($reviewer->can('view', $ownBranchOrder))->toBeTrue()
        ->and($reviewer->can('update', $ownBranchOrder))->toBeTrue()
        ->and($reviewer->can('updateStatus', $ownBranchOrder))->toBeTrue()
        ->and($reviewer->can('view', $otherBranchOrder))->toBeFalse()
        ->and($reviewer->can('update', $otherBranchOrder))->toBeFalse()
        ->and($reviewer->can('updateStatus', $otherBranchOrder))->toBeFalse();
});

it('still requires the matching permission for a user with access_all_branches', function () {
    $manager = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $order = StoreSalesOrder::factory()->create();

    // access_all_branches only widens branch scope; it never substitutes for a permission
    // (docs/store-sales-order-prd.md §16 "access_all_branches tetap membutuhkan permission fitur").
    expect($manager->can('view', $order))->toBeFalse();

    $manager->givePermissionTo(['view any store sales orders', 'view store sales orders']);

    expect($manager->can('view', $order))->toBeTrue();
});
