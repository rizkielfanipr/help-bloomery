<?php

use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemo;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lets only users with the matching permission view, create, and list memos', function () {
    $operator = User::factory()->create(['is_active' => true]);
    $operator->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo', 'create rnd internal memo']);
    $outsider = User::factory()->create(['is_active' => true]);
    $memo = RndInternalMemo::factory()->create();

    expect($operator->can('viewAny', RndInternalMemo::class))->toBeTrue()
        ->and($operator->can('view', $memo))->toBeTrue()
        ->and($operator->can('create', RndInternalMemo::class))->toBeTrue()
        ->and($outsider->can('viewAny', RndInternalMemo::class))->toBeFalse()
        ->and($outsider->can('view', $memo))->toBeFalse()
        ->and($outsider->can('create', RndInternalMemo::class))->toBeFalse();
});

it('only allows updating a memo while it is Draft', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('update rnd internal memo');

    foreach (RndInternalMemoStatus::cases() as $index => $status) {
        $memo = RndInternalMemo::factory()->create(['status' => $status, 'period_month' => now()->addMonths($index)->startOfMonth()]);

        expect($user->can('update', $memo))->toBe($status === RndInternalMemoStatus::Draft);
    }
});

it('only allows sync while the memo is Draft, NeedsAttention, or Ready', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('sync rnd internal memo');

    foreach (RndInternalMemoStatus::cases() as $index => $status) {
        $memo = RndInternalMemo::factory()->create(['status' => $status, 'period_month' => now()->addMonths($index)->startOfMonth()]);
        $expected = in_array($status, [RndInternalMemoStatus::Draft, RndInternalMemoStatus::NeedsAttention, RndInternalMemoStatus::Ready], true);

        expect($user->can('sync', $memo))->toBe($expected);
    }
});

it('only allows finalizing a memo that is Ready', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('finalize rnd internal memo');

    $ready = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Ready, 'period_month' => now()->startOfMonth()]);
    $draft = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft, 'period_month' => now()->addMonth()->startOfMonth()]);

    expect($user->can('finalize', $ready))->toBeTrue()
        ->and($user->can('finalize', $draft))->toBeFalse();
});

it('only allows creating a revision or generating a PDF from a Finalized memo', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['create rnd internal memo revision', 'generate rnd internal memo pdf']);

    $finalized = RndInternalMemo::factory()->finalized()->create(['period_month' => now()->startOfMonth()]);
    $ready = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Ready, 'period_month' => now()->addMonth()->startOfMonth()]);

    expect($user->can('createRevision', $finalized))->toBeTrue()
        ->and($user->can('generatePdf', $finalized))->toBeTrue()
        ->and($user->can('createRevision', $ready))->toBeFalse()
        ->and($user->can('generatePdf', $ready))->toBeFalse();
});

it('only allows archiving a Finalized memo and restoring an Archived one', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('archive rnd internal memo');

    $finalized = RndInternalMemo::factory()->finalized()->create(['period_month' => now()->startOfMonth()]);
    $archived = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Archived, 'period_month' => now()->addMonth()->startOfMonth()]);
    $draft = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft, 'period_month' => now()->addMonths(2)->startOfMonth()]);

    expect($user->can('archive', $finalized))->toBeTrue()
        ->and($user->can('archive', $archived))->toBeTrue()
        ->and($user->can('archive', $draft))->toBeFalse();
});

it('allows soft deleting an open memo with permission but protects syncing and finalized records', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('delete rnd internal memo');
    $draft = RndInternalMemo::factory()->create();
    $syncing = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Syncing]);
    $finalized = RndInternalMemo::factory()->finalized()->create();

    expect($user->can('delete', $draft))->toBeTrue()
        ->and($user->can('delete', $syncing))->toBeFalse()
        ->and($user->can('delete', $finalized))->toBeFalse();
});

it('grants SUPERADMIN every internal memo permission and RND_STAFF only the operator subset', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $rnd = User::factory()->create(['is_active' => true]);
    $rnd->assignRole('RND_STAFF');

    foreach ([
        'view any rnd internal memo', 'view rnd internal memo', 'create rnd internal memo',
        'update rnd internal memo', 'sync rnd internal memo', 'finalize rnd internal memo',
        'create rnd internal memo revision', 'generate rnd internal memo pdf',
        'download rnd internal memo pdf', 'archive rnd internal memo', 'manage rnd product shelf life',
        'delete rnd internal memo',
    ] as $permission) {
        expect($admin->can($permission))->toBeTrue();
    }

    foreach ([
        'view any rnd internal memo', 'view rnd internal memo', 'create rnd internal memo',
        'update rnd internal memo', 'sync rnd internal memo', 'download rnd internal memo pdf',
        'delete rnd internal memo',
        'manage rnd product shelf life',
    ] as $permission) {
        expect($rnd->can($permission))->toBeTrue();
    }

    foreach (['finalize rnd internal memo', 'create rnd internal memo revision', 'generate rnd internal memo pdf', 'archive rnd internal memo'] as $permission) {
        expect($rnd->can($permission))->toBeFalse();
    }
});
