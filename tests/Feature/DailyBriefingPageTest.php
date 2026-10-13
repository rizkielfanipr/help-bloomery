<?php

use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Filament\Casual\Pages\DailyBriefingPage;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingTask;
use App\Models\User;
use Database\Seeders\DailyBriefingTestSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(DailyBriefingTestSeeder::class);
    Cache::flush();
});

it('redirects guests to login', function () {
    get(route('filament.casual.pages.daily-briefing-page'))
        ->assertRedirect();
});

it('renders the daily briefing page for authenticated casual_staff', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user);

    Livewire::test(DailyBriefingPage::class)
        ->assertSee('Daily Briefing')
        ->assertSee('Harian')
        ->assertSee('Mingguan')
        ->assertSee('Bulanan');
});

it('shows all task labels for each period', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user);

    $component = Livewire::test(DailyBriefingPage::class);

    foreach (BriefingTask::all() as $task) {
        $component->assertSee($task->label);
    }
});

it('creates a briefing record and item when a task is saved with a photo', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

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
    expect($record->user_id)->toBe($user->id);

    $item = $record->items()->where('task_key', 'monthly_cleaning_chiller')->first();
    expect($item)->not->toBeNull();
    expect($item->notes)->toBe('Test catatan cleaning');
    expect($item->photo_paths)->toBe(['briefing-photos/test.jpg']);
    expect($item->review_status)->toBe(BriefingReviewStatus::SupervisorReview);
});

it('marks tasks as completed after saving with photo', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user);

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
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user);

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

it('updates completed count after saving tasks with photos', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user);

    $page = Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'weekly_cleaning')
        ->set('cameraPhotoPaths', ['briefing-photos/test1.jpg'])
        ->call('saveTask');

    $page->call('openTaskModal', 'weekly_wm_pic')
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
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user);

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'daily_selfie_pagi')
        ->call('saveTask');

    expect(BriefingRecord::count())->toBe(0);
});

it('saves daily_detail_briefing without photo when notes are filled', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user);

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
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user);

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', 'daily_detail_briefing')
        ->call('saveTask');

    expect(BriefingRecord::count())->toBe(0);
});

it('auto-rejects items for tasks past deadline', function () {
    BriefingTask::create([
        'key' => 'test_past_deadline',
        'label' => 'Test Past Deadline Task',
        'period' => 'daily',
        'submission_type' => 'photo',
        'deadline_enabled' => true,
        'deadline_time' => now()->subMinute()->format('H:i'),
        'sort_order' => 99,
        'is_active' => true,
    ]);

    BriefingTask::clearCache();

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    $this->artisan('briefing:auto-reject')->assertSuccessful();

    $record = BriefingRecord::where('user_id', $user->id)
        ->where('period', 'daily')
        ->first();

    expect($record)->not->toBeNull();

    $item = $record->items()->where('task_key', 'test_past_deadline')->first();
    expect($item)->not->toBeNull();
    expect($item->review_status)->toBe(BriefingReviewStatus::Rejected);
    expect($item->rejection_reason)->toBe('Tidak ada input sebelum deadline.');
});

it('does not auto-reject tasks before their deadline', function () {
    BriefingTask::create([
        'key' => 'test_future_deadline',
        'label' => 'Test Future Deadline Task',
        'period' => 'daily',
        'submission_type' => 'photo',
        'deadline_enabled' => true,
        'deadline_time' => now()->addHour()->format('H:i'),
        'sort_order' => 99,
        'is_active' => true,
    ]);

    BriefingTask::clearCache();

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    $this->artisan('briefing:auto-reject')->assertSuccessful();

    $record = BriefingRecord::where('user_id', $user->id)->where('period', 'daily')->first();
    $futureItem = $record?->items()->where('task_key', 'test_future_deadline')->first();

    expect($futureItem)->toBeNull();
});

it('does not auto-reject completed items still within the approval window', function () {
    BriefingTask::create([
        'key' => 'test_completed_task',
        'label' => 'Test Completed Task',
        'period' => 'daily',
        'submission_type' => 'photo',
        'deadline_enabled' => true,
        'deadline_time' => now()->subMinute()->format('H:i'),
        'sort_order' => 99,
        'is_active' => true,
    ]);

    BriefingTask::clearCache();

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    $record = BriefingRecord::create([
        'user_id' => $user->id,
        'period' => 'daily',
        'record_date' => today(),
        'submitted_at' => now(),
    ]);

    BriefingItem::create([
        'briefing_record_id' => $record->id,
        'task_key' => 'test_completed_task',
        'is_completed' => true,
        'completed_at' => now()->subMinutes(5),
        'review_status' => BriefingReviewStatus::SupervisorReview->value,
    ]);

    $this->artisan('briefing:auto-reject')->assertSuccessful();

    $item = $record->items()->where('task_key', 'test_completed_task')->first();
    expect($item->review_status)->toBe(BriefingReviewStatus::SupervisorReview);
});

it('auto-rejects completed items pending approval past the configured window (default 3 days)', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    $record = BriefingRecord::create([
        'user_id' => $user->id,
        'period' => 'daily',
        'record_date' => today()->subDays(3),
        'submitted_at' => now()->subDays(3),
    ]);

    $item = BriefingItem::create([
        'briefing_record_id' => $record->id,
        'task_key' => 'daily_selfie_pagi',
        'is_completed' => true,
        'completed_at' => now()->subDays(3)->subSecond(),
        'review_status' => BriefingReviewStatus::SupervisorReview->value,
    ]);

    $this->artisan('briefing:auto-reject')->assertSuccessful();

    $item->refresh();

    expect($item->review_status)->toBe(BriefingReviewStatus::Rejected)
        ->and($item->rejection_reason)->toBe('Tidak ada approval dalam 3 hari setelah poin diselesaikan.')
        ->and($item->reviewed_at)->not->toBeNull()
        ->and($item->reviewed_by)->toBeNull();
});
