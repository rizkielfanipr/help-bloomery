<?php

use App\Enums\StockCardStatus;
use App\Filament\Casual\Pages\StockCardEntryPage;
use App\Filament\Helpdesk\Resources\StockCards\Pages\ListStockCards;
use App\Filament\Helpdesk\Resources\StockCards\Pages\ViewStockCard;
use App\Models\Branch;
use App\Models\StockCard;
use App\Models\StockCardEntry;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->branch = Branch::factory()->create();
    $this->branch->esbCodes()->create(['esb_branch_code' => 'TST01', 'esb_comcode' => 'COM01']);

    $this->staff = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $this->staff->assignRole('STORE_STAFF');

    $this->card = StockCard::create([
        'branch_id' => $this->branch->id,
        'submitted_by' => $this->staff->id,
        'report_date' => today(),
        'flag_unit' => 'stockUnit',
        'submitted_at' => now(),
        'status' => StockCardStatus::PendingSupervisor->value,
    ]);

    $this->entry = StockCardEntry::create([
        'stock_card_id' => $this->card->id,
        'product_code' => 'MAT-001',
        'product_name' => 'Ayam',
        'system_qty' => null,
        'system_unit' => 'KG',
        'is_manual' => true,
        'actual_qty' => 5,
        'reported_qty' => 5,
        'notes' => null,
    ]);
});

function actingSupervisor(Branch $branch): User
{
    $supervisor = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    actingAs($supervisor);

    return $supervisor;
}

function fakeEsbMaterialUsage(float $totalQty = 5.0): void
{
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.COM01' => 'branch-token',
    ]);

    Http::fake([
        'https://sales-esb.test/corev1/sales/get-daily-sales-material-usage*' => Http::response([
            [
                'branchCode' => 'TST01',
                'branch' => 'Test Branch',
                'salesDate' => today()->toDateString(),
                'productCode' => 'MAT-001',
                'productName' => 'Ayam',
                'totalQty' => $totalQty,
                'unit' => 'KG',
                'totalConversionQty' => $totalQty,
                'unitConversion' => 'KG',
            ],
        ]),
    ]);
}

it('blocks review without the review stock cards as supervisor permission', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true, 'access_all_branches' => true]);
    $user->givePermissionTo('view stock cards');
    actingAs($user);

    expect(Livewire::test(ViewStockCard::class, ['record' => $this->card])->instance()->canReviewAsSupervisor())->toBeFalse();
});

it('prevents the submitting staff member from reviewing their own stock card', function () {
    $this->staff->assignRole('SUPERVISOR_STORE');
    actingAs($this->staff);

    expect(Livewire::test(ViewStockCard::class, ['record' => $this->card])->instance()->canReviewAsSupervisor())->toBeFalse();
});

it('blocks review when the card is not pending supervisor', function () {
    $this->card->update(['status' => StockCardStatus::Draft->value]);
    actingSupervisor($this->branch);

    expect(Livewire::test(ViewStockCard::class, ['record' => $this->card])->instance()->canReviewAsSupervisor())->toBeFalse();
});

it('blocks approveSupervisor until system data has been fetched from ESB', function () {
    actingSupervisor($this->branch);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->set("entryRows.{$this->entry->id}.actual_qty", '5')
        ->call('approveSupervisor')
        ->assertForbidden();

    expect($this->card->refresh()->status)->toBe(StockCardStatus::PendingSupervisor);
});

it('fetches system data from ESB and stores it on the entries', function () {
    fakeEsbMaterialUsage(totalQty: 4.0);
    actingSupervisor($this->branch);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('refetchEsb')
        ->assertHasNoErrors();

    $this->card->refresh();
    $this->entry->refresh();

    expect($this->card->system_fetched_at)->not->toBeNull()
        ->and((float) $this->entry->system_qty)->toBe(4.0);
});

it('tells the supervisor when ESB has no data yet instead of silently zeroing every entry', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.COM01' => 'branch-token',
    ]);
    Http::fake([
        'https://sales-esb.test/corev1/sales/get-daily-sales-material-usage*' => Http::response([]),
    ]);
    actingSupervisor($this->branch);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('refetchEsb')
        ->assertNotified('Belum ada data ESB untuk tanggal ini');

    $this->card->refresh();
    $this->entry->refresh();

    expect($this->card->system_fetched_at)->toBeNull()
        ->and($this->entry->system_qty)->toBeNull();
});

it('moves a stock card from supervisor approval through finance review once system data is fetched', function () {
    fakeEsbMaterialUsage(totalQty: 5.0);
    $supervisor = actingSupervisor($this->branch);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('refetchEsb')
        ->set("entryRows.{$this->entry->id}.actual_qty", '5')
        ->call('approveSupervisor')
        ->assertHasNoErrors();

    expect($this->card->refresh()->status)->toBe(StockCardStatus::PendingFinance)
        ->and($this->card->supervisor_reviewed_by)->toBe($supervisor->id);

    $finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $finance->assignRole('FINANCE_STAFF');
    actingAs($finance);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('approveFinance')
        ->assertHasNoErrors();

    expect($this->card->refresh()->status)->toBe(StockCardStatus::Completed)
        ->and($this->card->finance_reviewed_by)->toBe($finance->id)
        ->and($this->card->approvals()->count())->toBe(2);
});

it('requires supervisor notes when correcting a qty away from system data', function () {
    fakeEsbMaterialUsage(totalQty: 5.0);
    actingSupervisor($this->branch);

    $page = Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('refetchEsb')
        ->set("entryRows.{$this->entry->id}.actual_qty", '3')
        ->set("entryRows.{$this->entry->id}.supervisor_notes", '')
        ->call('approveSupervisor')
        ->assertHasErrors(["entryRows.{$this->entry->id}.supervisor_notes"]);

    expect($this->card->refresh()->status)->toBe(StockCardStatus::PendingSupervisor);
});

it('lets the supervisor correct the qty and persists it once notes are provided', function () {
    fakeEsbMaterialUsage(totalQty: 5.0);
    actingSupervisor($this->branch);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('refetchEsb')
        ->set("entryRows.{$this->entry->id}.actual_qty", '3')
        ->set("entryRows.{$this->entry->id}.supervisor_notes", 'Dihitung ulang, ada yang kelewat.')
        ->call('approveSupervisor')
        ->assertHasNoErrors();

    $this->entry->refresh();

    expect((float) $this->entry->actual_qty)->toBe(3.0)
        ->and((float) $this->entry->reported_qty)->toBe(5.0)
        ->and($this->entry->supervisor_notes)->toBe('Dihitung ulang, ada yang kelewat.')
        ->and($this->entry->variance)->toBe(-2.0);
});

it('returns a finance rejection to the supervisor with an audit trail and increments revision_number', function () {
    fakeEsbMaterialUsage(totalQty: 5.0);
    actingSupervisor($this->branch);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('refetchEsb')
        ->set("entryRows.{$this->entry->id}.actual_qty", '5')
        ->call('approveSupervisor')
        ->assertHasNoErrors();

    $finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $finance->assignRole('FINANCE_STAFF');
    actingAs($finance);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->set('rejectionReason', 'Ada produk yang belum dihitung ulang.')
        ->call('rejectFinance')
        ->assertHasNoErrors();

    $this->card->refresh();

    expect($this->card->status)->toBe(StockCardStatus::PendingSupervisor)
        ->and($this->card->revision_number)->toBe(1)
        ->and($this->card->approvals()->where('action', 'rejected')->exists())->toBeTrue();
});

it('requires a rejection reason before returning to the supervisor', function () {
    $this->card->update(['status' => StockCardStatus::PendingFinance->value]);
    $finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $finance->assignRole('FINANCE_STAFF');
    actingAs($finance);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->set('rejectionReason', '')
        ->call('rejectFinance')
        ->assertHasErrors(['rejectionReason']);

    expect($this->card->refresh()->status)->toBe(StockCardStatus::PendingFinance);
});

it('never reopens the staff entry page for editing even after a finance rejection bounces it back to supervisor', function () {
    $this->card->update(['status' => StockCardStatus::PendingSupervisor->value, 'revision_number' => 1]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->staff);

    Livewire::test(StockCardEntryPage::class, ['reportDate' => today()->toDateString()])
        ->assertSet('isSubmitted', true);
});

it('prevents supervisors from other branches from reviewing the card', function () {
    $supervisor = User::factory()->create(['branch_id' => Branch::factory()->create()->id, 'is_active' => true]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    actingAs($supervisor);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->assertForbidden();
});

it('shows status tabs with counts on the stock card index', function () {
    $admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $admin->givePermissionTo('view stock cards');
    actingAs($admin);

    StockCard::factory()->submitted()->create(['branch_id' => $this->branch->id, 'report_date' => today()->subDays(1)]);
    StockCard::factory()->submitted()->create(['branch_id' => $this->branch->id, 'report_date' => today()->subDays(2)]);
    StockCard::factory()->pendingFinance()->create(['branch_id' => $this->branch->id, 'report_date' => today()->subDays(3)]);
    StockCard::factory()->completed()->create(['branch_id' => $this->branch->id, 'report_date' => today()->subDays(4)]);

    $tabs = Livewire::test(ListStockCards::class)->instance()->getTabs();

    expect($tabs['pending_supervisor']->getBadge())->toBe('3')
        ->and($tabs['pending_finance']->getBadge())->toBe('1');
});

it('allows a superadmin to test both supervisor and finance approval stages', function () {
    fakeEsbMaterialUsage(totalQty: 5.0);

    $superadmin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $superadmin->assignRole('SUPERADMIN');
    actingAs($superadmin);

    $page = Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('refetchEsb')
        ->set("entryRows.{$this->entry->id}.actual_qty", '5')
        ->call('approveSupervisor')
        ->assertHasNoErrors();

    expect($this->card->refresh()->status)->toBe(StockCardStatus::PendingFinance);

    $page->call('approveFinance')->assertHasNoErrors();

    expect($this->card->refresh()->status)->toBe(StockCardStatus::Completed)
        ->and($this->card->supervisor_reviewed_by)->toBe($superadmin->id)
        ->and($this->card->finance_reviewed_by)->toBe($superadmin->id);
});

it('uses concise English labels throughout the stock card workflow', function () {
    expect(StockCardStatus::Draft->getLabel())->toBe('Draft')
        ->and(StockCardStatus::PendingSupervisor->getLabel())->toBe('Supervisor Review')
        ->and(StockCardStatus::PendingFinance->getLabel())->toBe('Finance Review')
        ->and(StockCardStatus::Completed->getLabel())->toBe('Completed');
});
