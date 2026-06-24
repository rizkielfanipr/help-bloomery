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

it('creates a briefing record and item when a task is saved with a photo', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    actingAs($user);

    expect(BriefingRecord::count())->toBe(0);

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', BriefingTaskKey::MonthlyGeneralCleaning->value)
        ->set('cameraPhotoPaths', ['briefing-photos/test.jpg'])
        ->set('taskData.notes', 'Test catatan cleaning')
        ->call('saveTask');

    expect(BriefingRecord::count())->toBe(1);

    $record = BriefingRecord::first();
    expect($record->period)->toBe(BriefingPeriod::Monthly);
    expect($record->user_id)->toBe($user->id);

    $item = $record->items()->where('task_key', BriefingTaskKey::MonthlyGeneralCleaning->value)->first();
    expect($item)->not->toBeNull();
    expect($item->notes)->toBe('Test catatan cleaning');
    expect($item->photo_paths)->toBe(['briefing-photos/test.jpg']);
});

it('marks tasks as completed after saving with photo', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    actingAs($user);

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', BriefingTaskKey::MonthlyGeneralCleaning->value)
        ->set('cameraPhotoPaths', ['briefing-photos/test.jpg'])
        ->call('saveTask');

    $record = BriefingRecord::where('period', BriefingPeriod::Monthly->value)->first();
    expect($record)->not->toBeNull();

    $item = $record->items()->where('task_key', BriefingTaskKey::MonthlyGeneralCleaning->value)->first();
    expect($item->is_completed)->toBeTrue();
    expect($item->completed_at)->not->toBeNull();
});

it('does not duplicate records when saving multiple tasks in the same period', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    actingAs($user);

    $page = Livewire::test(DailyBriefingPage::class);

    $page->call('openTaskModal', BriefingTaskKey::MonthlyGeneralCleaning->value)
        ->set('cameraPhotoPaths', ['briefing-photos/test1.jpg'])
        ->call('saveTask');

    $page->call('openTaskModal', BriefingTaskKey::MonthlyGmKpi->value)
        ->set('cameraPhotoPaths', ['briefing-photos/test2.jpg'])
        ->call('saveTask');

    expect(BriefingRecord::where('period', BriefingPeriod::Monthly->value)->count())->toBe(1);
    expect(BriefingItem::count())->toBe(2);
});

it('updates completed count after saving tasks with photos', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    actingAs($user);

    $page = Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', BriefingTaskKey::MonthlyGeneralCleaning->value)
        ->set('cameraPhotoPaths', ['briefing-photos/test1.jpg'])
        ->call('saveTask');

    $page->call('openTaskModal', BriefingTaskKey::MonthlyGmKpi->value)
        ->set('cameraPhotoPaths', ['briefing-photos/test2.jpg'])
        ->call('saveTask');

    expect(BriefingItem::count())->toBe(2);

    $page->assertSee('2/2 tugas selesai');
});

it('requires a photo before saving a task', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    actingAs($user);

    Livewire::test(DailyBriefingPage::class)
        ->call('openTaskModal', BriefingTaskKey::DailySelfiePagi->value)
        ->call('saveTask');

    expect(BriefingRecord::count())->toBe(0);
});
