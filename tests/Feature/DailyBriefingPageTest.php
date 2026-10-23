<?php

use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Filament\Casual\Pages\DailyBriefingPage;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingTask;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DailyBriefingTestSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * docs/daily-briefing-remediation-prd.md §4/§6/§10.1 (contract change from the pre-remediation
 * behavior these tests used to lock in): only an active STORE_STAFF user whose branch is an
 * explicit, enabled Daily Briefing participant may submit. The page used to accept any
 * CASUAL_STAFF regardless of branch — participantStoreStaff() is the new minimum fixture every
 * submission test needs instead.
 */
function participantStoreStaff(): User
{
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);

    $user = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $user->assignRole('STORE_STAFF');

    return $user;
}

beforeEach(function () {
    $this->seed(DailyBriefingTestSeeder::class);
    Cache::flush();
});

it('redirects guests to login', function () {
    get(route('filament.casual.pages.daily-briefing-page'))
        ->assertRedirect();
});

it('renders the daily briefing page for a participant store_staff', function () {
    actingAs(participantStoreStaff());

    Livewire::test(DailyBriefingPage::class)
        ->assertSee('Daily Briefing')
        ->assertSee('Request Backdate')
        ->assertSee('Harian')
        ->assertSee('Mingguan')
        ->assertSee('Bulanan');
});

it('denies page access to casual_staff (not a Daily Briefing participant role)', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');
    actingAs($user);

    expect(DailyBriefingPage::canAccess())->toBeFalse();
});

it('denies page access to a store_staff without a branch', function () {
    $user = User::factory()->create(['is_active' => true, 'branch_id' => null]);
    $user->assignRole('STORE_STAFF');
    actingAs($user);

    expect(DailyBriefingPage::canAccess())->toBeFalse();
});

it('denies page access to a store_staff whose branch is not an enabled participant', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $user->assignRole('STORE_STAFF');
    actingAs($user);

    expect(DailyBriefingPage::canAccess())->toBeFalse();
});

it('shows all globally-assigned task labels for each period', function () {
    actingAs(participantStoreStaff());

    $component = Livewire::test(DailyBriefingPage::class);

    foreach (BriefingTask::query()->whereNull('branch_id')->where('is_active', true)->get() as $task) {
        $component->assertSee($task->label);
    }
});

it('creates a briefing record and item when a task is saved with a photo', function () {
    $user = participantStoreStaff();
    actingAs($user);

    expect(BriefingRecord::count())->toBe(0);

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'monthly_cleaning_chiller')
        ->set('cameraPhotoPaths', ['briefing-photos/test.jpg'])
        ->set('taskData.notes', 'Test catatan cleaning')
        ->call('saveTask');

    expect(BriefingRecord::count())->toBe(1);

    $record = BriefingRecord::first();
    expect($record->period)->toBe(BriefingPeriod::Monthly);
    expect($record->branch_id)->toBe($user->branch_id);

    $item = $record->items()->where('task_key', 'monthly_cleaning_chiller')->first();
    expect($item)->not->toBeNull();
    expect($item->notes)->toBe('Test catatan cleaning');
    expect($item->photo_paths)->toBe(['briefing-photos/test.jpg']);
    expect($item->review_status)->toBe(BriefingReviewStatus::SupervisorReview);
});

it('marks tasks as completed after saving with photo', function () {
    actingAs(participantStoreStaff());

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'monthly_cleaning_chiller')
        ->set('cameraPhotoPaths', ['briefing-photos/test.jpg'])
        ->call('saveTask');

    $record = BriefingRecord::where('period', BriefingPeriod::Monthly->value)->first();
    expect($record)->not->toBeNull();

    $item = $record->items()->where('task_key', 'monthly_cleaning_chiller')->first();
    expect($item->is_completed)->toBeTrue();
    expect($item->completed_at)->not->toBeNull();
});

it('does not duplicate records when saving multiple tasks in the same period', function () {
    actingAs(participantStoreStaff());

    $page = Livewire::test(DailyBriefingPage::class);

    $page->call('openTaskModal', 'monthly_cleaning_chiller')
        ->set('cameraPhotoPaths', ['briefing-photos/test1.jpg'])
        ->call('saveTask');

    $page->call('openTaskModal', 'monthly_gm_kpi')
        ->set('cameraPhotoPaths', ['briefing-photos/test2.jpg'])
        ->call('saveTask');

    expect(BriefingRecord::where('period', BriefingPeriod::Monthly->value)->count())->toBe(1);
    expect(BriefingItem::count())->toBe(2);
});

it('shares one canonical record across two store_staff of the same branch (unit kewajiban = per branch)', function () {
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);

    $first = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $first->assignRole('STORE_STAFF');
    $second = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $second->assignRole('STORE_STAFF');

    actingAs($first);
    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'monthly_cleaning_chiller')
        ->set('cameraPhotoPaths', ['briefing-photos/test1.jpg'])
        ->call('saveTask');

    actingAs($second);
    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'monthly_gm_kpi')
        ->set('cameraPhotoPaths', ['briefing-photos/test2.jpg'])
        ->call('saveTask');

    expect(BriefingRecord::where('branch_id', $branch->id)->where('period', BriefingPeriod::Monthly->value)->count())->toBe(1)
        ->and(BriefingItem::count())->toBe(2);
});

it('updates completed count after saving tasks with photos', function () {
    actingAs(participantStoreStaff());

    $page = Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'weekly_wm_pic')
        ->set('cameraPhotoPaths', ['briefing-photos/test1.jpg'])
        ->call('saveTask');

    $page->call('openTaskModal', 'weekly_schedule')
        ->set('cameraPhotoPaths', ['briefing-photos/test2.jpg'])
        ->call('saveTask');

    expect(BriefingItem::count())->toBe(2);

    $weeklyTasks = BriefingTask::query()
        ->where('period', BriefingPeriod::Weekly->value)
        ->whereNull('branch_id')
        ->where('is_active', true)
        ->get();
    $completedWeeklyTaskCount = BriefingItem::query()
        ->whereIn('task_key', $weeklyTasks->pluck('key'))
        ->where('is_completed', true)
        ->count();

    $page->assertSee("{$completedWeeklyTaskCount}/{$weeklyTasks->count()} tugas selesai");
});

it('requires a photo before saving a task', function () {
    actingAs(participantStoreStaff());

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'daily_selfie_pagi')
        ->call('saveTask');

    expect(BriefingRecord::count())->toBe(0);
});

it('saves daily_detail_briefing without photo when notes are filled', function () {
    actingAs(participantStoreStaff());

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'daily_detail_briefing')
        ->set('taskData.notes', 'Isi detail briefing hari ini.')
        ->call('saveTask');

    $record = BriefingRecord::where('period', 'daily')->first();
    expect($record)->not->toBeNull();

    $item = $record->items()->where('task_key', 'daily_detail_briefing')->first();
    expect($item)->not->toBeNull();
    expect($item->notes)->toBe('Isi detail briefing hari ini.');
    expect($item->photo_paths)->toBeEmpty();
});

it('requires notes before saving daily_detail_briefing', function () {
    actingAs(participantStoreStaff());

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'daily_detail_briefing')
        ->call('saveTask');

    expect(BriefingRecord::count())->toBe(0);
});

it('rejects saveTask for a task key belonging to a different branch (Livewire task-key tampering)', function () {
    $otherBranch = Branch::factory()->create();
    BriefingTask::create([
        'branch_id' => $otherBranch->id,
        'key' => 'other_branch_only_task',
        'label' => 'Other Branch Only Task',
        'period' => 'daily',
        'submission_type' => 'checkbox',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    BriefingTask::clearCache();

    actingAs(participantStoreStaff());

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'other_branch_only_task')
        ->call('saveTask');

    expect(BriefingItem::where('task_key', 'other_branch_only_task')->exists())->toBeFalse();
});

it('keeps a weekly submission visible on another day in the same week', function () {
    Carbon::setTestNow('2026-10-12 10:00:00');
    $user = participantStoreStaff();
    $taskLabel = BriefingTask::query()->where('key', 'weekly_wm_pic')->value('label');
    actingAs($user);

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'weekly_wm_pic')
        ->set('cameraPhotoPaths', ['briefing-photos/weekly.jpg'])
        ->call('saveTask');

    Carbon::setTestNow('2026-10-15 10:00:00');

    $item = BriefingRecord::itemsForBranchPeriod(
        (int) $user->branch_id,
        BriefingPeriod::Weekly,
        now(),
    )->get('weekly_wm_pic');

    expect($item)->not->toBeNull()
        ->and($item->is_completed)->toBeTrue()
        ->and($item->photo_paths)->toBe(['briefing-photos/weekly.jpg'])
        ->and(BriefingRecord::where('period', BriefingPeriod::Weekly->value)->first()->record_date->toDateString())->toBe('2026-10-12');

    Livewire::test(DailyBriefingPage::class)
        ->assertSee($taskLabel)
        ->assertSee('Supervisor Review');
});

it('keeps a monthly submission visible on another day in the same month', function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    $user = participantStoreStaff();
    $taskLabel = BriefingTask::query()->where('key', 'monthly_cleaning_chiller')->value('label');
    actingAs($user);

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'monthly_cleaning_chiller')
        ->set('cameraPhotoPaths', ['briefing-photos/monthly.jpg'])
        ->call('saveTask');

    Carbon::setTestNow('2026-10-15 10:00:00');

    $item = BriefingRecord::itemsForBranchPeriod(
        (int) $user->branch_id,
        BriefingPeriod::Monthly,
        now(),
    )->get('monthly_cleaning_chiller');

    expect($item)->not->toBeNull()
        ->and($item->is_completed)->toBeTrue()
        ->and($item->photo_paths)->toBe(['briefing-photos/monthly.jpg'])
        ->and(BriefingRecord::where('period', BriefingPeriod::Monthly->value)->first()->record_date->toDateString())->toBe('2026-10-01');

    Livewire::test(DailyBriefingPage::class)
        ->assertSee($taskLabel)
        ->assertSee('Supervisor Review');
});

it('does not carry a daily submission into the next day', function () {
    Carbon::setTestNow('2026-10-14 10:00:00');
    $user = participantStoreStaff();
    actingAs($user);

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'daily_detail_briefing')
        ->set('taskData.notes', 'Briefing tanggal 14 Oktober')
        ->call('saveTask');

    Carbon::setTestNow('2026-10-15 10:00:00');

    expect(BriefingRecord::itemsForBranchPeriod(
        (int) $user->branch_id,
        BriefingPeriod::Daily,
        now(),
    )->has('daily_detail_briefing'))->toBeFalse();
});

it('prefers a completed legacy item over an empty duplicate in the same weekly period', function () {
    Carbon::setTestNow('2026-10-15 10:00:00');
    $user = participantStoreStaff();

    $emptyRecord = BriefingRecord::create([
        'user_id' => $user->id,
        'branch_id' => $user->branch_id,
        'period' => BriefingPeriod::Weekly->value,
        'record_date' => '2026-10-12',
    ]);
    $emptyRecord->items()->create([
        'task_key' => 'weekly_wm_pic',
        'is_completed' => false,
        'review_status' => BriefingReviewStatus::Rejected->value,
    ]);

    $completedRecord = BriefingRecord::create([
        'user_id' => $user->id,
        'branch_id' => $user->branch_id,
        'period' => BriefingPeriod::Weekly->value,
        'record_date' => '2026-10-14',
    ]);
    $completedItem = $completedRecord->items()->create([
        'task_key' => 'weekly_wm_pic',
        'photo_paths' => ['briefing-photos/legacy.jpg'],
        'is_completed' => true,
        'completed_at' => '2026-10-14 10:00:00',
        'review_status' => BriefingReviewStatus::SupervisorReview->value,
    ]);

    $visibleItem = BriefingRecord::itemsForBranchPeriod(
        (int) $user->branch_id,
        BriefingPeriod::Weekly,
        now(),
    )->get('weekly_wm_pic');

    expect($visibleItem?->is($completedItem))->toBeTrue();
});

it('keeps a submitted task visible as read-only after the task is deactivated', function () {
    Carbon::setTestNow('2026-10-15 10:00:00');
    $user = participantStoreStaff();
    $taskLabel = BriefingTask::query()->where('key', 'monthly_cleaning_chiller')->value('label');
    actingAs($user);

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'monthly_cleaning_chiller')
        ->set('cameraPhotoPaths', ['briefing-photos/inactive.jpg'])
        ->call('saveTask');

    BriefingTask::query()->where('key', 'monthly_cleaning_chiller')->update(['is_active' => false]);
    BriefingTask::clearCache();

    Livewire::test(DailyBriefingPage::class)
        ->assertSee($taskLabel)
        ->assertSee('Poin nonaktif')
        ->call('openTaskModal', 'monthly_cleaning_chiller')
        ->assertSet('activeTaskKey', null);
});

it('opens request backdate with yesterday selected and a one-month window', function () {
    Carbon::setTestNow('2026-10-23 10:00:00');
    actingAs(participantStoreStaff());

    Livewire::withQueryParams(['backdate' => 1])
        ->test(DailyBriefingPage::class)
        ->assertSet('requestBackdate', true)
        ->assertSet('briefingDate', '2026-10-22')
        ->assertSet('requestedBackdate', '2026-10-22')
        ->assertSee('Mode Backdate')
        ->assertSee('2026-09-23', escape: false)
        ->assertSee('2026-10-22', escape: false);
});

it('saves a missing daily point for the selected backdate', function () {
    Carbon::setTestNow('2026-10-23 10:00:00');
    $user = participantStoreStaff();
    actingAs($user);

    Livewire::withQueryParams(['backdate' => 1, 'date' => '2026-10-20'])
        ->test(DailyBriefingPage::class)
        ->call('openTaskModal', 'daily_detail_briefing')
        ->set('taskData.notes', 'Briefing backdate tanggal 20 Oktober.')
        ->call('saveTask');

    $record = BriefingRecord::query()
        ->where('branch_id', $user->branch_id)
        ->where('period', BriefingPeriod::Daily->value)
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->record_date->toDateString())->toBe('2026-10-20')
        ->and($record->items()->where('task_key', 'daily_detail_briefing')->exists())->toBeTrue();
});

it('rejects backdate outside the one-month window', function (string $date) {
    Carbon::setTestNow('2026-10-23 10:00:00');
    actingAs(participantStoreStaff());

    Livewire::withQueryParams(['backdate' => 1])
        ->test(DailyBriefingPage::class)
        ->set('requestedBackdate', $date)
        ->call('applyBackdate')
        ->assertHasErrors('requestedBackdate')
        ->assertSet('briefingDate', '2026-10-22');
})->with([
    'older than one month' => '2026-09-22',
    'today' => '2026-10-23',
    'future date' => '2026-10-24',
]);

it('hides points that already have a real submission in backdate mode', function () {
    Carbon::setTestNow('2026-10-23 10:00:00');
    $user = participantStoreStaff();
    $submittedTask = BriefingTask::query()->where('key', 'daily_detail_briefing')->firstOrFail();
    $missingTask = BriefingTask::query()->where('key', 'daily_selfie_pagi')->firstOrFail();

    $record = BriefingRecord::create([
        'user_id' => $user->id,
        'branch_id' => $user->branch_id,
        'period' => BriefingPeriod::Daily->value,
        'record_date' => '2026-10-20',
    ]);
    $record->items()->create([
        'task_key' => $submittedTask->key,
        'notes' => 'Sudah pernah diinput.',
        'is_completed' => true,
        'completed_at' => '2026-10-20 09:00:00',
        'review_status' => BriefingReviewStatus::SupervisorReview->value,
    ]);

    actingAs($user);

    $component = Livewire::withQueryParams(['backdate' => 1, 'date' => '2026-10-20'])
        ->test(DailyBriefingPage::class);

    $visibleDailyTaskKeys = collect($component->get('briefingData')['daily']['tasks'])->pluck('key');

    expect($visibleDailyTaskKeys)->not->toContain($submittedTask->key)
        ->and($visibleDailyTaskKeys)->toContain($missingTask->key);

    $component
        ->call('openTaskModal', $submittedTask->key)
        ->assertSet('activeTaskKey', null);
});
