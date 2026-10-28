<?php

use App\Actions\Briefing\BriefingReviewAction;
use App\Enums\BriefingReviewStatus;
use App\Filament\Helpdesk\Resources\BriefingItems\BriefingItemResource;
use App\Filament\Helpdesk\Resources\BriefingItems\Pages\ListBriefingItems;
use App\Models\Branch;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * docs/products/daily-briefing/remediation/prd.md §10.2/§10.3/§12.2/§25.1 (Phase 1/4 safety net).
 * Monitoring Poin, review actions, and exports must stay scoped to accessibleBranchIds() unless
 * the actor canAccessAllBranches() — and that scope must come from briefing_records.branch_id
 * (the snapshot), never from the submitter's current users.branch_id.
 */
function briefingItemIn(Branch $branch, array $overrides = []): BriefingItem
{
    $staff = User::factory()->create(array_merge(['is_active' => true, 'branch_id' => $branch->id], $overrides['user'] ?? []));

    $record = BriefingRecord::create([
        'user_id' => $staff->id,
        'branch_id' => $branch->id,
        'period' => 'daily',
        'record_date' => now()->toDateString(),
        'submitted_at' => now(),
    ]);

    return BriefingItem::create(array_merge([
        'briefing_record_id' => $record->id,
        'task_key' => 'daily_selfie_pagi',
        'photo_paths' => ['briefing-photos/test.jpg'],
        'is_completed' => true,
        'completed_at' => now(),
        'review_status' => BriefingReviewStatus::SupervisorReview->value,
    ], $overrides['item'] ?? []));
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->ownBranch = Branch::factory()->create();
    $this->otherBranch = Branch::factory()->create();
});

it('restricts Monitoring Poin to the supervisor\'s accessibleBranchIds()', function () {
    $ownItem = briefingItemIn($this->ownBranch);
    $otherItem = briefingItemIn($this->otherBranch);

    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $supervisor->syncBranchAccess([$this->ownBranch->id], $this->ownBranch->id);

    actingAs($supervisor);

    Livewire::test(ListBriefingItems::class)
        ->assertCanSeeTableRecords([$ownItem])
        ->assertCanNotSeeTableRecords([$otherItem]);
});

it('lets canAccessAllBranches() see every branch in Monitoring Poin', function () {
    $ownItem = briefingItemIn($this->ownBranch);
    $otherItem = briefingItemIn($this->otherBranch);

    $admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $admin->givePermissionTo('view briefing items');

    actingAs($admin);

    Livewire::test(ListBriefingItems::class)
        ->assertCanSeeTableRecords([$ownItem, $otherItem]);
});

it('returns no rows for a user without the view briefing items permission (fail closed, not fail open)', function () {
    $ownItem = briefingItemIn($this->ownBranch);

    $user = User::factory()->create(['is_active' => true]);
    actingAs($user);

    expect(BriefingItemResource::getEloquentQuery()->count())->toBe(0);
});

it('rejects a supervisor approving an item outside accessibleBranchIds(), even calling the action directly', function () {
    $otherItem = briefingItemIn($this->otherBranch);

    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $supervisor->syncBranchAccess([$this->ownBranch->id], $this->ownBranch->id);

    $result = app(BriefingReviewAction::class)->approve($supervisor, $otherItem);

    expect($result->successful)->toBeFalse()
        ->and($otherItem->refresh()->review_status)->toBe(BriefingReviewStatus::SupervisorReview);
});

it('rejects a supervisor rejecting an item outside accessibleBranchIds(), even calling the action directly', function () {
    $otherItem = briefingItemIn($this->otherBranch);

    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $supervisor->syncBranchAccess([$this->ownBranch->id], $this->ownBranch->id);

    $result = app(BriefingReviewAction::class)->reject($supervisor, $otherItem, 'Alasan.');

    expect($result->successful)->toBeFalse()
        ->and($otherItem->refresh()->review_status)->toBe(BriefingReviewStatus::SupervisorReview);
});

it('allows a supervisor to approve an item within accessibleBranchIds()', function () {
    $ownItem = briefingItemIn($this->ownBranch);

    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $supervisor->syncBranchAccess([$this->ownBranch->id], $this->ownBranch->id);

    $result = app(BriefingReviewAction::class)->approve($supervisor, $ownItem);

    expect($result->successful)->toBeTrue()
        ->and($ownItem->refresh()->review_status)->toBe(BriefingReviewStatus::Approved);
});

it('rejects approve/reject from a user who merely has edit briefing items but lacks review briefing items', function () {
    $ownItem = briefingItemIn($this->ownBranch);

    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $user->givePermissionTo(['view briefing items', 'edit briefing items']);

    $result = app(BriefingReviewAction::class)->approve($user, $ownItem);

    expect($result->successful)->toBeFalse();
});

it('rejects the briefing Excel export for a branch_id outside the requester\'s access', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('view briefing records');
    $user->syncBranchAccess([$this->ownBranch->id], $this->ownBranch->id);
    actingAs($user);

    get(route('helpdesk.exports.briefing', [
        'branch_id' => $this->otherBranch->id,
        'period' => 'daily',
        'year' => now()->year,
        'month' => now()->month,
    ]))->assertForbidden();
});

it('keeps a submission scoped to its branch snapshot after the submitter changes branch', function () {
    $originalBranch = $this->ownBranch;
    $newBranch = $this->otherBranch;

    $item = briefingItemIn($originalBranch);
    $staff = $item->record->user;

    $staff->update(['branch_id' => $newBranch->id]);

    $supervisorOfOriginal = User::factory()->create(['is_active' => true]);
    $supervisorOfOriginal->assignRole('SUPERVISOR_STORE');
    $supervisorOfOriginal->syncBranchAccess([$originalBranch->id], $originalBranch->id);

    actingAs($supervisorOfOriginal);

    // The record's branch_id snapshot is unchanged, so the supervisor of the *original* branch
    // still sees it even though the staff member has since moved to a different branch.
    Livewire::test(ListBriefingItems::class)
        ->assertCanSeeTableRecords([$item]);

    expect($item->record->refresh()->branch_id)->toBe($originalBranch->id);
});
