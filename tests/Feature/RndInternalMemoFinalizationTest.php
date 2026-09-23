<?php

use App\Enums\RndInternalMemoMenuSyncStatus;
use App\Enums\RndInternalMemoStatus;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ViewRndInternalMemo;
use App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

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
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->supervisor = User::factory()->create(['is_active' => true]);
    $this->supervisor->givePermissionTo([
        'view any rnd internal memo', 'view rnd internal memo', 'update rnd internal memo',
        'finalize rnd internal memo', 'create rnd internal memo revision', 'archive rnd internal memo',
        'delete rnd internal memo',
    ]);
    $this->actingAs($this->supervisor);
});

it('finalizes a Ready memo, recording the actor, timestamp, and a snapshot hash', function () {
    $memo = readyInternalMemo();

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('finalizeMemo');

    $memo->refresh();
    expect($memo->status)->toBe(RndInternalMemoStatus::Finalized)
        ->and($memo->finalized_by)->toBe($this->supervisor->id)
        ->and($memo->finalized_at)->not->toBeNull()
        ->and($memo->snapshot_hash)->not->toBeNull();
});

it('refuses to finalize a memo that still has a blocker', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Ready, 'source_synced_at' => now()]);
    RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id, 'forecast_quantity' => 0]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('finalizeMemo');

    expect($memo->fresh()->status)->toBe(RndInternalMemoStatus::Ready);
});

it('refuses to finalize a memo that is not Ready', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('finalizeMemo')->assertForbidden();
});

it('hides the Finalisasi button from a user without the finalize permission', function () {
    $operator = User::factory()->create(['is_active' => true]);
    $operator->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo']);
    $this->actingAs($operator);
    $memo = readyInternalMemo();

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->assertDontSee('Finalisasi');
});

it('creates a revision from a Finalized memo, copying Menu and Forecast but resetting sync_status', function () {
    $memo = readyInternalMemo();
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('finalizeMemo');
    $memo->refresh();

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openRevisionModal')
        ->set('revisionMemoNumber', $memo->memo_number.'-R2')
        ->call('createRevision')
        ->assertHasNoErrors();

    $revision = RndInternalMemo::query()->where('memo_number', $memo->memo_number.'-R2')->sole();
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
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('finalizeMemo');

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openRevisionModal')
        ->set('revisionMemoNumber', $memo->memo_number)
        ->call('createRevision')
        ->assertHasNoErrors();

    expect(RndInternalMemo::query()->where('memo_number', $memo->memo_number)->count())->toBe(1);
});

it('archives a Finalized memo and can restore it back', function () {
    $memo = readyInternalMemo();
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('finalizeMemo');

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('toggleArchive');
    $memo->refresh();
    expect($memo->status)->toBe(RndInternalMemoStatus::Archived)
        ->and($memo->archived_by)->toBe($this->supervisor->id)
        ->and($memo->archived_at)->not->toBeNull();

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('toggleArchive');
    $memo->refresh();
    expect($memo->status)->toBe(RndInternalMemoStatus::Finalized)
        ->and($memo->archived_by)->toBeNull();
});

it('does not let a Draft memo be archived', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('toggleArchive')->assertForbidden();
});

it('soft deletes an open memo while preserving its Menu data', function () {
    $memo = readyInternalMemo();
    $menuId = $memo->menus()->sole()->id;

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('deleteMemo')
        ->assertRedirect(RndInternalMemoResource::getUrl('index'));

    $this->assertSoftDeleted('rnd_internal_memos', ['id' => $memo->id]);
    $this->assertDatabaseHas('rnd_internal_memo_menus', ['id' => $menuId, 'rnd_internal_memo_id' => $memo->id]);
});

it('does not let a Finalized memo be deleted', function () {
    $memo = readyInternalMemo();
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('finalizeMemo');

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('deleteMemo')
        ->assertForbidden();

    $this->assertDatabaseHas('rnd_internal_memos', ['id' => $memo->id, 'deleted_at' => null]);
});
