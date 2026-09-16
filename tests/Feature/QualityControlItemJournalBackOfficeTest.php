<?php

use App\Filament\Helpdesk\Resources\QualityControlItemJournals\Pages\ListQualityControlItemJournals;
use App\Filament\Helpdesk\Resources\QualityControlItemJournals\Pages\ViewQualityControlItemJournal;
use App\Filament\Helpdesk\Resources\QualityControlItemJournals\QualityControlItemJournalResource;
use App\Models\QualityControlItemJournal;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    Permission::findOrCreate('access backoffice');
    Permission::findOrCreate('view quality control item journals');
    Permission::findOrCreate('view all quality control item journals');

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->givePermissionTo(['access backoffice', 'view quality control item journals']);
    $this->actingAs($this->user);
});

it('provides a read only item journal submenu in quality control', function () {
    $journal = QualityControlItemJournal::query()->create([
        'created_by' => $this->user->id,
        'item_journal_number' => 'IU202609160001',
        'esb_comcode' => 'BLSS',
        'esb_branch_id' => 373,
        'esb_branch_code' => 'BLA',
        'esb_branch_name' => 'Bloomery Test',
        'location_id' => 964,
        'location_name' => 'Kitchen',
        'journal_date' => today(),
        'status' => 'succeeded',
    ]);

    expect(QualityControlItemJournalResource::getNavigationGroup())->toBe('Quality Control')
        ->and(Route::has('filament.helpdesk.resources.quality-control-item-journals.create'))->toBeFalse()
        ->and(Route::has('filament.helpdesk.resources.quality-control-item-journals.edit'))->toBeFalse();

    $this->get(route('filament.helpdesk.resources.quality-control-item-journals.index'))
        ->assertOk()
        ->assertSee(route('filament.helpdesk.resources.quality-control-item-journals.index'), false);

    Livewire::test(ListQualityControlItemJournals::class)
        ->assertCanSeeTableRecords([$journal])
        ->assertSee('IU202609160001')
        ->assertSee('Bloomery Test');

    Livewire::test(ViewQualityControlItemJournal::class, ['record' => $journal->id])
        ->assertSee('IU202609160001')
        ->assertSee('Data Integrasi ESB');
});

it('only lists another users journals with the view all permission', function () {
    $otherUser = User::factory()->create();
    $otherJournal = QualityControlItemJournal::query()->create([
        'created_by' => $otherUser->id,
        'esb_comcode' => 'BLSS',
        'esb_branch_id' => 373,
        'esb_branch_code' => 'BLA',
        'location_id' => 964,
        'location_name' => 'Kitchen',
        'journal_date' => today(),
        'status' => 'succeeded',
    ]);

    Livewire::test(ListQualityControlItemJournals::class)
        ->assertCanNotSeeTableRecords([$otherJournal]);

    $this->user->givePermissionTo('view all quality control item journals');

    Livewire::test(ListQualityControlItemJournals::class)
        ->assertCanSeeTableRecords([$otherJournal]);
});
