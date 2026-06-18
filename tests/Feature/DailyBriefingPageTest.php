<?php

use App\Enums\BriefingPeriod;
use App\Enums\BriefingTaskKey;
use App\Filament\Casual\Pages\DailyBriefingPage;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('redirects guests to login', function () {
    get(route('filament.casual.pages.daily-briefing-page'))
        ->assertRedirect();
});

it('renders the daily briefing page for authenticated casual_staff', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    actingAs($user);

    Livewire::test(DailyBriefingPage::class)
        ->assertSee('Daily Briefing')
        ->assertSee('Harian')
        ->assertSee('Mingguan')
        ->assertSee('Bulanan');
});

it('shows all task labels for each period', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    actingAs($user);

    $component = Livewire::test(DailyBriefingPage::class);

    foreach (BriefingTaskKey::cases() as $task) {
        $component->assertSee($task->getLabel());
    }
});

it('creates a briefing record and item when an HR-checked task is saved', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    actingAs($user);

    expect(BriefingRecord::count())->toBe(0);

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', BriefingTaskKey::MonthlyMilestone->value)
        ->set('taskData.notes', 'Test catatan milestone')
        ->call('saveTask');

    expect(BriefingRecord::count())->toBe(1);

    $record = BriefingRecord::first();
    expect($record->period)->toBe(BriefingPeriod::Monthly);
    expect($record->user_id)->toBe($user->id);

    $item = $record->items()->where('task_key', BriefingTaskKey::MonthlyMilestone->value)->first();
    expect($item)->not->toBeNull();
    expect($item->notes)->toBe('Test catatan milestone');
});

it('marks HR-checked tasks as pending review (not completed)', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    actingAs($user);

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', BriefingTaskKey::MonthlyMilestone->value)
        ->set('taskData.notes', 'Milestone note')
        ->call('saveTask');

    $record = BriefingRecord::where('period', BriefingPeriod::Monthly->value)->first();
    expect($record)->not->toBeNull();

    $item = $record->items()->where('task_key', BriefingTaskKey::MonthlyMilestone->value)->first();
    expect($item->is_completed)->toBeFalse();
    expect($item->completed_at)->toBeNull();
});

it('does not duplicate records when saving multiple tasks in the same period', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    actingAs($user);

    $page = Livewire::test(DailyBriefingPage::class);

    $page->call('openTaskModal', BriefingTaskKey::MonthlyMilestone->value)
        ->set('taskData.notes', 'Milestone note')
        ->call('saveTask');

    $page->call('openTaskModal', BriefingTaskKey::MonthlyKpi->value)
        ->set('taskData.notes', 'KPI note')
        ->call('saveTask');

    expect(BriefingRecord::where('period', BriefingPeriod::Monthly->value)->count())->toBe(1);
    expect(BriefingItem::count())->toBe(2);
});

it('updates completed count after saving a task', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    actingAs($user);

    // Save two HR-checked tasks (no photo required)
    $page = Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', BriefingTaskKey::MonthlyMilestone->value)
        ->set('taskData.notes', 'Milestone')
        ->call('saveTask');

    $page->call('openTaskModal', BriefingTaskKey::MonthlyKpi->value)
        ->set('taskData.notes', 'KPI')
        ->call('saveTask');

    // Both items should be in the DB
    expect(BriefingItem::count())->toBe(2);

    // Monthly period shows 0/4 completed (HR tasks are pending, not marked complete)
    $page->assertSee('0/4 tugas selesai');
});
