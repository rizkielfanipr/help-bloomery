<?php

use App\Enums\BriefingPeriod;
use App\Filament\Helpdesk\Resources\BriefingItems\Pages\ListBriefingItems;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->givePermissionTo('view briefing items');

    actingAs($this->admin);

    $this->staff = User::factory()->create(['is_active' => true]);

    $this->record = BriefingRecord::create([
        'user_id' => $this->staff->id,
        'period' => BriefingPeriod::Daily->value,
        'record_date' => now()->toDateString(),
        'submitted_at' => now(),
    ]);
});

it('shows the detail action for text-only items with notes but no photos', function () {
    $item = BriefingItem::create([
        'briefing_record_id' => $this->record->id,
        'task_key' => 'daily_detail_briefing',
        'notes' => 'Hari ini briefing membahas target penjualan bulan ini.',
        'photo_paths' => null,
        'is_completed' => true,
        'completed_at' => now(),
    ]);

    Livewire::test(ListBriefingItems::class)
        ->assertActionVisible(TestAction::make('view_photos')->table($item));
});

it('shows the detail action for items with photos', function () {
    $item = BriefingItem::create([
        'briefing_record_id' => $this->record->id,
        'task_key' => 'daily_selfie_pagi',
        'photo_paths' => ['briefing-photos/test.jpg'],
        'is_completed' => true,
        'completed_at' => now(),
    ]);

    Livewire::test(ListBriefingItems::class)
        ->assertActionVisible(TestAction::make('view_photos')->table($item));
});

it('hides the detail action for items with no notes and no photos', function () {
    $item = BriefingItem::create([
        'briefing_record_id' => $this->record->id,
        'task_key' => 'daily_selfie_sore',
        'notes' => null,
        'photo_paths' => null,
        'is_completed' => false,
    ]);

    Livewire::test(ListBriefingItems::class)
        ->assertActionHidden(TestAction::make('view_photos')->table($item));
});
