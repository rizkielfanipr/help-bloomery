<?php

use App\Models\Branch;
use App\Models\CustomerComplaint;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lets only users with the matching permission view any, create, update, and delete complaints', function () {
    $operator = User::factory()->create(['is_active' => true]);
    $operator->givePermissionTo([
        'view any customer complaints', 'view customer complaints',
        'create customer complaints', 'update customer complaints', 'delete customer complaints',
    ]);
    $outsider = User::factory()->create(['is_active' => true]);
    $branch = Branch::factory()->create();
    $operator->branch_id = $branch->id;
    $operator->save();
    $complaint = CustomerComplaint::factory()->create(['branch_id' => $branch->id]);

    expect($operator->can('viewAny', CustomerComplaint::class))->toBeTrue()
        ->and($operator->can('view', $complaint))->toBeTrue()
        ->and($operator->can('create', CustomerComplaint::class))->toBeTrue()
        ->and($operator->can('update', $complaint))->toBeTrue()
        ->and($operator->can('delete', $complaint))->toBeTrue()
        ->and($outsider->can('viewAny', CustomerComplaint::class))->toBeFalse()
        ->and($outsider->can('view', $complaint))->toBeFalse()
        ->and($outsider->can('create', CustomerComplaint::class))->toBeFalse()
        ->and($outsider->can('update', $complaint))->toBeFalse()
        ->and($outsider->can('delete', $complaint))->toBeFalse();
});

it('always lets the submitter view their own complaint, even without the view permission or branch access', function () {
    $otherBranch = Branch::factory()->create();
    $submitter = User::factory()->create(['is_active' => true, 'branch_id' => $otherBranch->id]);
    $complaint = CustomerComplaint::factory()->create([
        'submitted_by' => $submitter->id,
        'branch_id' => Branch::factory()->create()->id,
    ]);

    expect($submitter->can('view', $complaint))->toBeTrue();
});

it('restricts a reviewer to complaints within their accessible branches', function () {
    $accessibleBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $accessibleBranch->id]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints', 'update customer complaints']);

    $ownBranchComplaint = CustomerComplaint::factory()->create(['branch_id' => $accessibleBranch->id]);
    $otherBranchComplaint = CustomerComplaint::factory()->create(['branch_id' => $otherBranch->id]);

    expect($reviewer->can('view', $ownBranchComplaint))->toBeTrue()
        ->and($reviewer->can('update', $ownBranchComplaint))->toBeTrue()
        ->and($reviewer->can('view', $otherBranchComplaint))->toBeFalse()
        ->and($reviewer->can('update', $otherBranchComplaint))->toBeFalse();
});

it('still requires the matching permission for a user with access_all_branches', function () {
    $manager = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $complaint = CustomerComplaint::factory()->create();

    // access_all_branches only widens branch scope; it never substitutes for a permission
    // (docs/customer-complaints-prd.md §12 "User dengan access_all_branches tetap membutuhkan
    // permission yang sesuai").
    expect($manager->can('view', $complaint))->toBeFalse();

    $manager->givePermissionTo(['view any customer complaints', 'view customer complaints']);

    expect($manager->can('view', $complaint))->toBeTrue();
});

it('lets a supervisor with access_all_branches view complaints from every branch once permitted', function () {
    $manager = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $manager->givePermissionTo(['view any customer complaints', 'view customer complaints']);

    $complaintA = CustomerComplaint::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    $complaintB = CustomerComplaint::factory()->create(['branch_id' => Branch::factory()->create()->id]);

    expect($manager->can('view', $complaintA))->toBeTrue()
        ->and($manager->can('view', $complaintB))->toBeTrue();
});
