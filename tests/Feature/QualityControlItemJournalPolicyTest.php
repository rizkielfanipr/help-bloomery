<?php

use App\Models\QualityControlItemJournal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->owner = User::factory()->create(['is_active' => true]);
    $this->journal = QualityControlItemJournal::query()->create([
        'created_by' => $this->owner->id,
        'esb_comcode' => 'BLSS',
        'esb_branch_id' => 373,
        'esb_branch_code' => 'BLA',
        'location_id' => 964,
        'location_name' => 'Kitchen',
        'journal_date' => today(),
        'status' => 'succeeded',
    ]);
});

it('allows a viewer to see only their own journals by default', function () {
    $this->owner->givePermissionTo('view quality control item journals');
    $other = User::factory()->create(['is_active' => true]);
    $other->givePermissionTo('view quality control item journals');

    expect($this->owner->can('view', $this->journal))->toBeTrue()
        ->and($other->can('view', $this->journal))->toBeFalse()
        ->and(QualityControlItemJournal::query()->visibleTo($other)->exists())->toBeFalse();
});

it('allows view-all users to see journals created by another user', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo([
        'view quality control item journals',
        'view all quality control item journals',
    ]);

    expect($viewer->can('view', $this->journal))->toBeTrue()
        ->and(QualityControlItemJournal::query()->visibleTo($viewer)->pluck('id')->all())
        ->toBe([$this->journal->id]);
});

it('requires view create and submit permissions for submission', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo([
        'view quality control item journals',
        'create quality control item journals',
    ]);

    expect($user->can('create', QualityControlItemJournal::class))->toBeTrue()
        ->and($user->can('submit', QualityControlItemJournal::class))->toBeFalse();

    $user->givePermissionTo('submit quality control item journals');

    expect($user->can('submit', QualityControlItemJournal::class))->toBeTrue();
});

it('requires ownership and delete attachment permission', function () {
    $this->owner->givePermissionTo([
        'view quality control item journals',
        'delete quality control item journal attachments',
    ]);
    $other = User::factory()->create(['is_active' => true]);
    $other->givePermissionTo([
        'view quality control item journals',
        'delete quality control item journal attachments',
    ]);

    expect($this->owner->can('deleteAttachments', $this->journal))->toBeTrue()
        ->and($other->can('deleteAttachments', $this->journal))->toBeFalse();
});
