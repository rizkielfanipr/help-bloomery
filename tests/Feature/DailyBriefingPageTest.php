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
