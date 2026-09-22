<?php

use App\Models\GoodsReceipt;
use App\Models\User;
use App\Models\VendorComplianceIncident;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('requires the employee Receiving permission to access and submit goods receipts', function () {
    $user = User::factory()->create(['is_active' => true]);

    expect($user->can('accessEmployeeApp', GoodsReceipt::class))->toBeFalse()
        ->and($user->can('submit', GoodsReceipt::class))->toBeFalse();

    $user->givePermissionTo('access employee app goods receipt');

    expect($user->can('accessEmployeeApp', GoodsReceipt::class))->toBeTrue()
        ->and($user->can('submit', GoodsReceipt::class))->toBeTrue();
});

it('keeps the back office Receiving resource read only', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo('view goods receipts');
    $receipt = GoodsReceipt::factory()->create(['branch_id' => 999999]);

    expect($viewer->can('viewAny', GoodsReceipt::class))->toBeTrue()
        ->and($viewer->can('view', $receipt))->toBeTrue()
        ->and($viewer->can('create', GoodsReceipt::class))->toBeFalse()
        ->and($viewer->can('update', $receipt))->toBeFalse()
        ->and($viewer->can('delete', $receipt))->toBeFalse();
});

it('requires separate view and edit permissions for vendor compliance follow up', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo('view vendor compliance incidents');
    $incident = VendorComplianceIncident::factory()->create();

    expect($viewer->can('view', $incident))->toBeTrue()
        ->and($viewer->can('update', $incident))->toBeFalse();

    $viewer->givePermissionTo('edit vendor compliance incidents');

    expect($viewer->can('update', $incident))->toBeTrue();
});

it('keeps vendor compliance creation and deletion system managed', function () {
    $purchasing = User::factory()->create(['is_active' => true]);
    $purchasing->givePermissionTo([
        'view vendor compliance incidents',
        'edit vendor compliance incidents',
    ]);
    $incident = VendorComplianceIncident::factory()->create();

    expect($purchasing->can('create', VendorComplianceIncident::class))->toBeFalse()
        ->and($purchasing->can('delete', $incident))->toBeFalse();
});
