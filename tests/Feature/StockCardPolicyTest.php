<?php

use App\Enums\StockCardStatus;
use App\Models\Branch;
use App\Models\StockCard;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->otherBranch = Branch::factory()->create();
});

it('scopes stock cards to the users accessible branches', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $visibleCard = StockCard::factory()->create(['branch_id' => $this->branch->id]);
    StockCard::factory()->create(['branch_id' => $this->otherBranch->id]);

    expect(StockCard::query()->accessibleTo($user)->pluck('id')->all())
        ->toBe([$visibleCard->id]);
});

it('allows all-branch users to query every stock card', function () {
    $user = User::factory()->create(['access_all_branches' => true, 'is_active' => true]);
    StockCard::factory()->create(['branch_id' => $this->branch->id]);
    StockCard::factory()->create(['branch_id' => $this->otherBranch->id]);

    expect(StockCard::query()->accessibleTo($user)->count())->toBe(2);
});

it('requires view permission and branch access to view a stock card', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $user->givePermissionTo('view stock cards');
    $localCard = StockCard::factory()->create(['branch_id' => $this->branch->id]);
    $otherCard = StockCard::factory()->create(['branch_id' => $this->otherBranch->id]);

    expect($user->can('viewAny', StockCard::class))->toBeTrue()
        ->and($user->can('view', $localCard))->toBeTrue()
        ->and($user->can('view', $otherCard))->toBeFalse();
});

it('requires delete permission and branch access to delete a stock card', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $user->givePermissionTo(['view stock cards', 'delete stock cards']);
    $localCard = StockCard::factory()->create(['branch_id' => $this->branch->id]);
    $otherCard = StockCard::factory()->create(['branch_id' => $this->otherBranch->id]);

    expect($user->can('delete', $localCard))->toBeTrue()
        ->and($user->can('delete', $otherCard))->toBeFalse();
});

it('preserves supervisor review restrictions for status branch and own submissions', function () {
    $supervisor = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $supervisor->givePermissionTo(['view stock cards', 'review stock cards as supervisor']);
    $card = StockCard::factory()->create([
        'branch_id' => $this->branch->id,
        'submitted_by' => $supervisor->id,
        'status' => StockCardStatus::PendingSupervisor,
    ]);

    expect($supervisor->can('reviewAsSupervisor', $card))->toBeFalse();

    $card->update(['submitted_by' => User::factory()->create()->id]);
    expect($supervisor->can('reviewAsSupervisor', $card))->toBeTrue();

    $card->update(['status' => StockCardStatus::PendingFinance]);
    expect($supervisor->can('reviewAsSupervisor', $card))->toBeFalse();
});

it('allows the applicable reviewer to refresh ESB data while preserving the all-branch exception', function () {
    $submitter = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $card = StockCard::factory()->create([
        'branch_id' => $this->branch->id,
        'submitted_by' => $submitter->id,
        'status' => StockCardStatus::PendingSupervisor,
    ]);
    $supervisor = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $supervisor->givePermissionTo(['view stock cards', 'review stock cards as supervisor']);

    expect($supervisor->can('refreshEsb', $card))->toBeTrue();

    $card->update(['submitted_by' => $supervisor->id]);
    expect($supervisor->can('refreshEsb', $card))->toBeFalse();

    $supervisor->update(['access_all_branches' => true]);
    expect($supervisor->can('refreshEsb', $card))->toBeTrue();
});
