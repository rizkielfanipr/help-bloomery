<?php

use App\Filament\Casual\Pages\ItemJournalPage;
use App\Filament\Helpdesk\Resources\QualityControlItemJournals\QualityControlItemJournalResource;
use App\Models\QualityControlItemJournal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

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

it('opens the Employee App page with the tile permission instead of Quality Control access', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['access employee app item journal', 'view quality control item journals']);

    $this->actingAs($user)
        ->get(route('filament.casual.pages.item-journal-page'))
        ->assertOk()
        ->assertSee('Item Journal')
        ->assertDontSee(route('filament.casual.pages.quality-control-audits'), false);
});

it('refuses the direct page URL without the tile permission or journal view access', function (array $permissions) {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo($permissions);

    $this->actingAs($user)
        ->get(route('filament.casual.pages.item-journal-page'))
        ->assertForbidden();
})->with([
    'Quality Control and journal actions without tile' => [['access employee app quality control', 'view quality control item journals', 'create quality control item journals', 'submit quality control item journals']],
    'tile only' => [['access employee app item journal']],
    'another Employee App tile' => [['access employee app stock card']],
]);

it('does not let the tile permission replace the create and submit permissions', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['access employee app item journal', 'view quality control item journals']);
    $this->actingAs($user);

    Livewire::test(ItemJournalPage::class)
        ->assertDontSee('Buat Item Journal')
        ->call('openForm')
        ->assertForbidden();
    Livewire::test(ItemJournalPage::class)->call('submit')->assertForbidden();

    expect(QualityControlItemJournal::query()->count())->toBe(1);
});

it('grants the seeded role defaults from the PRD', function (string $role, array $expected) {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);
    $this->actingAs($user);

    expect([
        'page' => ItemJournalPage::canAccess(),
        'create' => $user->can('create', QualityControlItemJournal::class),
        'submit' => $user->can('submit', QualityControlItemJournal::class),
        'back office' => QualityControlItemJournalResource::canViewAny(),
        'others' => $user->can('view', $this->journal),
    ])->toBe($expected);
})->with([
    'STORE_STAFF' => ['STORE_STAFF', ['page' => true, 'create' => true, 'submit' => true, 'back office' => true, 'others' => false]],
    'INVENTORY_STAFF' => ['INVENTORY_STAFF', ['page' => false, 'create' => false, 'submit' => false, 'back office' => true, 'others' => false]],
    'QUALITY_CONTROL' => ['QUALITY_CONTROL', ['page' => true, 'create' => true, 'submit' => true, 'back office' => true, 'others' => false]],
    'QUALITY_CONTROL_SUPERVISOR' => ['QUALITY_CONTROL_SUPERVISOR', ['page' => false, 'create' => false, 'submit' => false, 'back office' => true, 'others' => true]],
    'SUPERADMIN' => ['SUPERADMIN', ['page' => true, 'create' => true, 'submit' => true, 'back office' => true, 'others' => true]],
]);

it('keeps journals without a local Branch visible to view-all users and owners only', function () {
    $viewAll = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $viewAll->givePermissionTo(['view quality control item journals', 'view all quality control item journals']);
    $restricted = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $restricted->givePermissionTo('view quality control item journals');

    expect($this->journal->branch_id)->toBeNull()
        ->and($viewAll->can('view', $this->journal))->toBeTrue()
        ->and($restricted->can('view', $this->journal))->toBeFalse()
        ->and(QualityControlItemJournal::query()->visibleTo($restricted)->exists())->toBeFalse();
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
