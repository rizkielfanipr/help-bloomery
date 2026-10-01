<?php

use App\Actions\Rnd\InternalMemo\ArchiveInternalMemoAction;
use App\Actions\Rnd\InternalMemo\CreateInternalMemoRevisionAction;
use App\Actions\Rnd\InternalMemo\DeleteInternalMemoAction;
use App\Actions\Rnd\InternalMemo\FinalizeInternalMemoAction;
use App\Enums\RndInternalMemoMenuSyncStatus;
use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

/**
 * docs/rnd-internal-memo-simplification-prd.md Phase 4/5: the simplified workspace
 * (ViewRndInternalMemo) no longer exposes finalize/revision/archive/PDF controls — "UI sederhana
 * tidak menampilkan ... finalisasi, revisi". These Actions and their RndInternalMemoPolicy gates
 * still exist for the transition period (§5.2), so this file now exercises them directly instead
 * of through the removed Livewire UI methods. Permission/Policy coverage for these abilities
 * lives in RndInternalMemoPermissionTest.php; the "button hidden" assertions that used to live
 * here were redundant with that file once the buttons no longer exist at all.
 */
function readyInternalMemo(): RndInternalMemo
{
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Ready, 'source_synced_at' => now()]);
    RndInternalMemoMenu::factory()->create([
        'rnd_internal_memo_id' => $memo->id,
        'forecast_quantity' => 10,
        'shelf_life_value' => 3,
        'shelf_life_unit' => 'hari',
        'sync_status' => RndInternalMemoMenuSyncStatus::Synced,
        'sync_error' => null,
    ]);

    return $memo;
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->supervisor = User::factory()->create(['is_active' => true]);
    $this->actingAs($this->supervisor);
});

it('finalizes a Ready memo, recording the actor, timestamp, and a snapshot hash', function () {
    $memo = readyInternalMemo();

    app(FinalizeInternalMemoAction::class)->execute($memo, $this->supervisor);

    $memo->refresh();
    expect($memo->status)->toBe(RndInternalMemoStatus::Finalized)
        ->and($memo->finalized_by)->toBe($this->supervisor->id)
        ->and($memo->finalized_at)->not->toBeNull()
        ->and($memo->snapshot_hash)->not->toBeNull();
});

it('refuses to finalize a memo that still has a blocker', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Ready, 'source_synced_at' => now()]);
    RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id, 'forecast_quantity' => 0]);

    expect(fn () => app(FinalizeInternalMemoAction::class)->execute($memo, $this->supervisor))
        ->toThrow(RuntimeException::class);

    expect($memo->fresh()->status)->toBe(RndInternalMemoStatus::Ready);
});

it('refuses to finalize a memo that is not Ready', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);

    expect(fn () => app(FinalizeInternalMemoAction::class)->execute($memo, $this->supervisor))
        ->toThrow(RuntimeException::class);
});

it('creates a revision from a Finalized memo, copying Menu and Forecast but resetting sync_status', function () {
    $memo = readyInternalMemo();
    app(FinalizeInternalMemoAction::class)->execute($memo, $this->supervisor);
    $memo->refresh();

    $revision = app(CreateInternalMemoRevisionAction::class)->execute($memo, $memo->memo_number.'-R2', $this->supervisor);

    expect($revision->revision)->toBe($memo->revision + 1)
        ->and($revision->status)->toBe(RndInternalMemoStatus::Draft)
        ->and($revision->period_month->toDateString())->toBe($memo->period_month->toDateString());

    $copiedMenu = $revision->menus()->sole();
    $originalMenu = $memo->menus()->sole();
    expect((float) $copiedMenu->forecast_quantity)->toBe((float) $originalMenu->forecast_quantity)
        ->and($copiedMenu->sync_status)->toBe(RndInternalMemoMenuSyncStatus::Pending);
});

it('refuses a revision memo number that already exists', function () {
    $memo = readyInternalMemo();
    app(FinalizeInternalMemoAction::class)->execute($memo, $this->supervisor);

    expect(fn () => app(CreateInternalMemoRevisionAction::class)->execute($memo, $memo->memo_number, $this->supervisor))
        ->toThrow(ValidationException::class);

    expect(RndInternalMemo::query()->where('memo_number', $memo->memo_number)->count())->toBe(1);
});

it('archives a Finalized memo and can restore it back', function () {
    $memo = readyInternalMemo();
    app(FinalizeInternalMemoAction::class)->execute($memo, $this->supervisor);

    app(ArchiveInternalMemoAction::class)->execute($memo, $this->supervisor);
    $memo->refresh();
    expect($memo->status)->toBe(RndInternalMemoStatus::Archived)
        ->and($memo->archived_by)->toBe($this->supervisor->id)
        ->and($memo->archived_at)->not->toBeNull();

    app(ArchiveInternalMemoAction::class)->execute($memo, $this->supervisor);
    $memo->refresh();
    expect($memo->status)->toBe(RndInternalMemoStatus::Finalized)
        ->and($memo->archived_by)->toBeNull();
});

it('does not let a Draft memo be archived', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);

    expect(fn () => app(ArchiveInternalMemoAction::class)->execute($memo, $this->supervisor))
        ->toThrow(RuntimeException::class);
});

it('soft deletes an open memo while preserving its Menu data', function () {
    $memo = readyInternalMemo();
    $menuId = $memo->menus()->sole()->id;

    app(DeleteInternalMemoAction::class)->execute($memo);

    $this->assertSoftDeleted('rnd_internal_memos', ['id' => $memo->id]);
    $this->assertDatabaseHas('rnd_internal_memo_menus', ['id' => $menuId, 'rnd_internal_memo_id' => $memo->id]);
});

it('does not let a Finalized memo be deleted', function () {
    $memo = readyInternalMemo();
    app(FinalizeInternalMemoAction::class)->execute($memo, $this->supervisor);

    expect(fn () => app(DeleteInternalMemoAction::class)->execute($memo))
        ->toThrow(RuntimeException::class);

    $this->assertDatabaseHas('rnd_internal_memos', ['id' => $memo->id, 'deleted_at' => null]);
});
