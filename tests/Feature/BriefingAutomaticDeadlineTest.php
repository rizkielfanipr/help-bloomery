<?php

use App\Enums\BriefingPeriod;
use App\Filament\Helpdesk\Resources\BriefingTasks\Pages\CreateBriefingTask;
use App\Models\BriefingTask;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

afterEach(function () {
    Carbon::setTestNow();
});

it('automatically assigns Sunday at 23:59 to weekly points on create and update', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-29 10:00:00', 'Asia/Jakarta'));

    $task = BriefingTask::query()->create([
        'key' => 'automatic_weekly_deadline',
        'label' => 'Automatic Weekly Deadline',
        'period' => BriefingPeriod::Weekly->value,
        'submission_type' => 'camera_only',
        'deadline_enabled' => false,
        'deadline_day' => 2,
        'deadline_time' => '10:00',
        'is_active' => true,
    ]);

    expect($task->deadline_enabled)->toBeTrue()
        ->and($task->deadline_day)->toBe(7)
        ->and(substr($task->deadline_time, 0, 5))->toBe('23:59')
        ->and($task->deadlineAt()?->format('Y-m-d H:i'))->toBe('2026-08-02 23:59')
        ->and($task->deadlineLabel())->toBe('Batas: Minggu 23.59');

    $task->update([
        'deadline_enabled' => false,
        'deadline_day' => 1,
        'deadline_time' => '08:00',
    ]);

    expect($task->refresh()->deadline_enabled)->toBeTrue()
        ->and($task->deadline_day)->toBe(7)
        ->and(substr($task->deadline_time, 0, 5))->toBe('23:59');
});

it('uses the actual last day of each month at 23:59', function () {
    Carbon::setTestNow(Carbon::parse('2028-02-10 12:00:00', 'Asia/Jakarta'));

    $task = BriefingTask::query()->create([
        'key' => 'automatic_monthly_deadline',
        'label' => 'Automatic Monthly Deadline',
        'period' => BriefingPeriod::Monthly->value,
        'submission_type' => 'camera_only',
        'deadline_enabled' => false,
        'is_active' => true,
    ]);

    expect($task->deadline_day)->toBe(0)
        ->and(substr($task->deadline_time, 0, 5))->toBe('23:59')
        ->and($task->deadlineAt()?->format('Y-m-d H:i'))->toBe('2028-02-29 23:59')
        ->and($task->deadlineLabel())->toBe('Batas: Akhir bulan 23.59');

    Carbon::setTestNow(Carbon::parse('2027-02-10 12:00:00', 'Asia/Jakarta'));
    expect($task->deadlineAt()?->format('Y-m-d H:i'))->toBe('2027-02-28 23:59');

    Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', 'Asia/Jakarta'));
    expect($task->deadlineAt()?->format('Y-m-d H:i'))->toBe('2026-04-30 23:59');
});

it('normalizes old weekly and monthly points through the deployment migration', function () {
    DB::table('briefing_tasks')->insert([
        [
            'key' => 'legacy_weekly_point',
            'label' => 'Legacy Weekly',
            'period' => 'weekly',
            'submission_type' => 'camera_only',
            'note_type' => '',
            'sort_order' => 1,
            'is_active' => true,
            'deadline_enabled' => false,
            'deadline_day' => null,
            'deadline_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'key' => 'legacy_monthly_point',
            'label' => 'Legacy Monthly',
            'period' => 'monthly',
            'submission_type' => 'camera_only',
            'note_type' => '',
            'sort_order' => 1,
            'is_active' => true,
            'deadline_enabled' => false,
            'deadline_day' => 15,
            'deadline_time' => '12:00',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $migration = require database_path('migrations/2026_07_29_000001_set_automatic_weekly_monthly_briefing_deadlines.php');
    $migration->up();

    $this->assertDatabaseHas('briefing_tasks', [
        'key' => 'legacy_weekly_point',
        'deadline_enabled' => true,
        'deadline_day' => 7,
        'deadline_time' => '23:59:00',
    ]);
    $this->assertDatabaseHas('briefing_tasks', [
        'key' => 'legacy_monthly_point',
        'deadline_enabled' => true,
        'deadline_day' => 0,
        'deadline_time' => '23:59:00',
    ]);
});

it('keeps automatic weekly and monthly deadline fields visible as readonly information', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    $page = Livewire::test(CreateBriefingTask::class)
        ->fillForm(['period' => BriefingPeriod::Weekly->value])
        ->assertSee('Hari Batas')
        ->assertSee('Minggu')
        ->assertSee('Jam Batas')
        ->assertSet('data.deadline_day', 7)
        ->assertSet('data.deadline_time', '23:59');

    $page->fillForm(['period' => BriefingPeriod::Monthly->value])
        ->assertSee('Tanggal Batas')
        ->assertSee('Jam Batas')
        ->assertSet('data.monthly_deadline_label', 'Tanggal terakhir bulan')
        ->assertSet('data.deadline_day', 0)
        ->assertSet('data.deadline_time', '23:59');
});
