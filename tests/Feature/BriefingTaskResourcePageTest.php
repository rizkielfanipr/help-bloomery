<?php

use App\Filament\Helpdesk\Resources\BriefingTasks\Pages\ListBriefingTasks;
use App\Models\Branch;
use App\Models\BriefingTask;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

use function Pest\Laravel\actingAs;

/**
 * Smoke test for the Kelola Poin page's new header/summary chrome
 * (docs/products/daily-briefing/scoring-ui/prd.md §13.1 visual pattern applied to BriefingTaskResource).
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
});
it('renders the branch-picker view with the header and summary stats over HTTP', function () {
    Branch::factory()->create(['is_active' => true]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    $this->get(route('filament.helpdesk.resources.briefing-tasks.index'))
        ->assertOk()
        ->assertSee('Kelola Poin Briefing')
        ->assertSee('Total Poin')
        ->assertSee('Pilih branch untuk melihat dan mengelola poin briefingnya.');
});

it('renders the task view with the header once a branch is selected', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->assertSee('Kelola Poin Briefing')
        ->assertSee($branch->name);
});

it('shows a clear empty state explaining the Global fallback for a period with no branch-specific tasks', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->assertSee('Belum ada poin khusus Harian untuk branch ini.')
        ->assertSee('Pakai Global')
        ->assertSee('Tambah Poin')
        ->assertSee('Buat Grup');
});

it('renders the restyled copy panel when toggled open', function () {
    $source = Branch::factory()->create(['is_active' => true, 'name' => 'Source Branch']);
    $target = Branch::factory()->create(['is_active' => true]);
    BriefingTask::create([
        'branch_id' => $source->id,
        'key' => 'kelola_poin_copy_panel_test',
        'label' => 'Poin Sumber',
        'period' => 'daily',
        'submission_type' => 'checkbox',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $target->id, $target->name)
        ->call('toggleCopyPanel')
        ->assertSee('Salin Poin Briefing')
        ->assertSee('role="dialog"', false)
        ->set('copySourceBranchId', $source->id)
        ->assertSee('Salin 1 Poin')
        ->assertSee('Poin Sumber');
});

it('opens the Tambah Poin modal and saves a new task into it', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    $component = Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->call('startPeriodQuickAdd', 'daily')
        ->assertSee('Tambah Poin Briefing')
        ->assertSee('role="dialog"', false)
        ->set('quickAdd.label', 'Poin Baru Via Modal')
        ->call('saveQuickTask');

    expect(BriefingTask::where('branch_id', $branch->id)->where('label', 'Poin Baru Via Modal')->exists())->toBeTrue();

    // Quick-add stays open after saving so another task can be added right away.
    $component->assertSee('Tambah Poin Briefing');
});

it('shows the revised submission type labels but not the legacy Centang Selesai option in the Tambah Poin modal', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->call('startPeriodQuickAdd', 'daily')
        ->assertSee('Foto dari Kamera')
        ->assertSee('Unggah Foto')
        ->assertSee('Foto & Catatan')
        ->assertSee('Catatan Teks')
        ->assertDontSee('Centang Selesai');
});

it('still offers Centang Selesai in the Edit modal when the task already uses it', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    $checkboxTask = BriefingTask::create([
        'branch_id' => $branch->id,
        'key' => 'kelola_poin_legacy_checkbox_test',
        'label' => 'Poin Legacy',
        'period' => 'daily',
        'submission_type' => 'checkbox',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->call('startEditTask', $checkboxTask->id)
        ->assertSeeHtml('wire:click="$set(\'editTask.submission_type\', \'checkbox\')"');
});

it('does not offer Centang Selesai in the Edit modal for a task that already uses another type', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    $photoTask = BriefingTask::create([
        'branch_id' => $branch->id,
        'key' => 'kelola_poin_photo_test',
        'label' => 'Poin Foto',
        'period' => 'daily',
        'submission_type' => 'photo',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->call('startEditTask', $photoTask->id)
        ->assertDontSeeHtml('wire:click="$set(\'editTask.submission_type\', \'checkbox\')"');
});

it('saves a daily task created via the Tambah Poin modal with a deadline enabled', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->call('startPeriodQuickAdd', 'daily')
        ->assertSee('Aktifkan Deadline')
        ->set('quickAdd.label', 'Poin Dengan Deadline')
        ->set('quickAdd.deadline_enabled', true)
        ->set('quickAdd.deadline_time', '18:30')
        ->call('saveQuickTask');

    $task = BriefingTask::where('branch_id', $branch->id)->where('label', 'Poin Dengan Deadline')->first();

    expect($task)->not->toBeNull()
        ->and($task->deadline_enabled)->toBeTrue()
        ->and($task->deadline_time)->toBe('18:30');
});

it('opens the Edit Poin modal, updates a task, and the Edit resource page no longer exists', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    $task = BriefingTask::create([
        'branch_id' => $branch->id,
        'key' => 'kelola_poin_edit_modal_test',
        'label' => 'Poin Lama',
        'period' => 'daily',
        'submission_type' => 'checkbox',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->call('startEditTask', $task->id)
        ->assertSee('Edit Poin Briefing')
        ->assertSee('role="dialog"', false)
        ->set('editTask.label', 'Poin Baru')
        ->set('editTask.deadline_enabled', true)
        ->set('editTask.deadline_time', '09:00')
        ->call('saveEditTask')
        ->assertDontSee('Edit Poin Briefing');

    $task->refresh();

    expect($task->label)->toBe('Poin Baru')
        ->and($task->deadline_enabled)->toBeTrue()
        ->and($task->deadline_time)->toBe('09:00');

    expect(fn () => route('filament.helpdesk.resources.briefing-tasks.edit', ['record' => $task->id]))
        ->toThrow(RouteNotFoundException::class);
});

it('deletes a task via the Hapus Poin button inside the Edit modal', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    $task = BriefingTask::create([
        'branch_id' => $branch->id,
        'key' => 'kelola_poin_edit_modal_delete_test',
        'label' => 'Poin Untuk Dihapus',
        'period' => 'daily',
        'submission_type' => 'checkbox',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    $component = Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->call('startEditTask', $task->id)
        ->call('deleteFromEditModal', $task->id)
        ->assertDontSee('Edit Poin Briefing');

    expect($component->get('pendingDeleteId'))->toBe($task->id);

    $component->call('deleteTask', $task->id);

    expect(BriefingTask::find($task->id))->toBeNull();
});

it('opens the Buat Grup modal and creates a group with a single point in one submit', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->call('startNewGroup', 'daily')
        ->assertSee('Buat Grup Baru')
        ->assertSee('Poin di Grup Ini')
        ->set('newGroupLabel', 'Grup Modal Test')
        ->set('newGroupTasks.0.label', 'Poin Pertama Grup')
        ->call('saveNewGroup')
        ->assertDontSee('Buat Grup Baru');

    expect(BriefingTask::where('branch_id', $branch->id)
        ->where('label', 'Poin Pertama Grup')
        ->where('group_label', 'Grup Modal Test')
        ->exists())->toBeTrue();
});

it('creates a group with several points added at once via the repeater rows', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->call('startNewGroup', 'daily')
        ->set('newGroupLabel', 'Grup Multi Poin')
        ->set('newGroupTasks.0.label', 'Poin A')
        ->call('addNewGroupTaskRow')
        ->set('newGroupTasks.1.label', 'Poin B')
        ->call('addNewGroupTaskRow')
        ->set('newGroupTasks.2.label', 'Poin C')
        ->call('saveNewGroup')
        ->assertDontSee('Buat Grup Baru');

    $tasks = BriefingTask::where('branch_id', $branch->id)->where('group_label', 'Grup Multi Poin')->pluck('label');

    expect($tasks)->toHaveCount(3)
        ->and($tasks)->toContain('Poin A', 'Poin B', 'Poin C');
});

it('ignores a blank repeater row but still saves the rest when creating a group', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->call('startNewGroup', 'daily')
        ->set('newGroupLabel', 'Grup Dengan Baris Kosong')
        ->set('newGroupTasks.0.label', 'Poin Terisi')
        ->call('addNewGroupTaskRow')
        ->call('saveNewGroup');

    expect(BriefingTask::where('group_label', 'Grup Dengan Baris Kosong')->count())->toBe(1);
});

it('keeps the Buat Grup modal open with a validation error when no point has a label', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->call('startNewGroup', 'daily')
        ->set('newGroupLabel', 'Grup Tanpa Poin')
        ->set('newGroupTasks.0.label', '')
        ->call('saveNewGroup')
        ->assertHasErrors(['newGroupTasks.0.label'])
        ->assertSee('Buat Grup Baru');

    expect(BriefingTask::where('group_label', 'Grup Tanpa Poin')->exists())->toBeFalse();
});

it('removes a repeater row when asked, keeping at least one row', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    $component = Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->call('startNewGroup', 'daily')
        ->call('addNewGroupTaskRow')
        ->call('addNewGroupTaskRow');

    expect($component->get('newGroupTasks'))->toHaveCount(3);

    $component->call('removeNewGroupTaskRow', 1);
    expect($component->get('newGroupTasks'))->toHaveCount(2);

    $component->call('removeNewGroupTaskRow', 0)
        ->call('removeNewGroupTaskRow', 0);

    expect($component->get('newGroupTasks'))->toHaveCount(1);
});

it('keeps only one modal open at a time between Salin Poin, Tambah Poin, and Buat Grup', function () {
    $branch = Branch::factory()->create(['is_active' => true]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    $component = Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->call('toggleCopyPanel');

    expect($component->get('copyPanelOpen'))->toBeTrue();

    $component->call('startPeriodQuickAdd', 'daily');

    expect($component->get('copyPanelOpen'))->toBeFalse()
        ->and($component->get('addingToPeriod'))->toBe('daily');

    $component->call('startNewGroup', 'weekly');

    expect($component->get('addingToPeriod'))->toBeNull()
        ->and($component->get('creatingGroupForPeriod'))->toBe('weekly');

    $component->call('toggleCopyPanel');

    expect($component->get('creatingGroupForPeriod'))->toBeNull()
        ->and($component->get('copyPanelOpen'))->toBeTrue();
});

it('hides the empty-state message for a period once it has a task', function () {
    $branch = Branch::factory()->create(['is_active' => true]);
    BriefingTask::create([
        'branch_id' => $branch->id,
        'key' => 'kelola_poin_empty_state_test',
        'label' => 'Poin Khusus Test',
        'period' => 'daily',
        'submission_type' => 'checkbox',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view briefing records', 'access backoffice']);
    actingAs($user);

    Livewire::test(ListBriefingTasks::class)
        ->call('selectBranch', $branch->id, $branch->name)
        ->assertSee('Poin Khusus Test')
        ->assertDontSee('Belum ada poin khusus Harian untuk branch ini.');
});
