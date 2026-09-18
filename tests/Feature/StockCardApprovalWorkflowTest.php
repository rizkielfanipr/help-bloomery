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
use Illuminate\Support\Facades\Cache;
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

function fakeEsbStockMovement(float $totalQty = 5.0): void
{
    config()->set('esb.core.base_url', 'https://core-esb.test');
    Cache::put('esb_core.access_token.COM01', 'branch-token', 300);
    Http::fake([
        'https://core-esb.test/report/stock-movement*' => Http::response([
            'status' => 'ok',
            'result' => ['count' => 1, 'next' => '', 'data' => [[
                'branchCode' => 'TST01', 'location' => 'Kitchen',
                'documentDate' => today()->toDateString(), 'createdDate' => today()->toDateTimeString(),
                'productCode' => 'MAT-001', 'productName' => 'Ayam',
                'qtyBalance' => $totalQty, 'UOM' => 'KG',
            ]]],
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
    fakeEsbStockMovement(totalQty: 4.0);
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
    config()->set('esb.core.base_url', 'https://core-esb.test');
    Cache::put('esb_core.access_token.COM01', 'branch-token', 300);
    Http::fake([
        'https://core-esb.test/report/stock-movement*' => Http::response([
            'status' => 'ok', 'result' => ['data' => [], 'count' => 0, 'next' => ''],
        ]),
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
    fakeEsbStockMovement(totalQty: 5.0);
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
    fakeEsbStockMovement(totalQty: 5.0);
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
    fakeEsbStockMovement(totalQty: 5.0);
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
    fakeEsbStockMovement(totalQty: 5.0);
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
    fakeEsbStockMovement(totalQty: 5.0);

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

it('leaves all system quantities unverified when a mapped company fails', function () {
    fakeEsbStockMovement();
    $this->branch->esbCodes()->create(['esb_comcode' => 'BLO6', 'esb_branch_code' => 'BL6', 'is_active' => true]);
    Cache::put('esb_core.access_token.BLO6', 'blo6-token', 300);
    Http::fake(function ($request) {
        if ($request['branchCode'] === 'BL6') {
            return Http::response(['status' => 'fail', 'message' => 'Company unavailable'], 503);
        }

        return Http::response(['status' => 'ok', 'result' => [
            'data' => [[
                'branchCode' => 'TST01', 'productCode' => 'MAT-001', 'UOM' => 'KG',
                'location' => 'Kitchen', 'documentDate' => today()->toDateString(), 'qtyBalance' => 5,
            ]], 'count' => 1, 'next' => '',
        ]]);
    });
    actingSupervisor($this->branch);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('refetchEsb')
        ->assertNotified('Gagal mengambil Stock Movement');

    expect($this->card->refresh()->system_fetched_at)->toBeNull()
        ->and($this->entry->refresh()->system_qty)->toBeNull();
});

it('shows every API transaction type without changing saved quantities or approval', function () {
    config()->set('esb.core.base_url', 'https://core-esb.test');
    Cache::put('esb_core.access_token.COM01', 'branch-token', 300);
    Http::preventStrayRequests();
    $row = [
        'branchCode' => 'TST01', 'productCode' => 'MAT-001', 'productName' => 'Ayam', 'UOM' => 'KG',
        'location' => 'Kitchen', 'documentDate' => today()->toDateString(), 'qtyBalance' => 10,
    ];
    Http::fake(['*stock-movement*' => Http::response(['status' => 'ok', 'result' => [
        'data' => [
            $row + ['transactionType' => 'Goods Receipt', 'qtyIn' => 2, 'qtyOut' => 0],
            $row + ['transactionType' => 'Goods Receipt', 'qtyIn' => 3, 'qtyOut' => 0],
            $row + ['transactionType' => 'Item Journal', 'qtyIn' => 0, 'qtyOut' => 1.5],
            array_replace($row, ['productCode' => 'OTHER', 'transactionType' => 'New API Type', 'qtyIn' => 1, 'qtyOut' => 0]),
        ], 'next' => '', 'count' => 4,
    ]])]);
    $this->card->update(['status' => StockCardStatus::Completed->value]);
    $this->entry->update(['system_qty' => 99]);
    actingSupervisor($this->branch);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('loadTransactionBreakdown')
        ->assertSet('transactionTypes', ['Beginning', 'Goods Delivery', 'Goods Receipt', 'Item Journal', 'New API Type', 'POS Sales', 'Purchase Invoice Adjustment'])
        ->assertSet('transactionQuantities.MAT-001.Goods Receipt.qty_in', 5.0)
        ->assertSet('transactionQuantities.MAT-001.Item Journal.qty_out', 1.5)
        ->assertSee('Qty Utama')
        ->assertSee('Goods Receipt')
        ->assertSee('New API Type')
        ->assertSee('Masuk 5')
        ->assertSee('Keluar 1.5')
        ->assertSee('Refresh Rincian Transaksi');

    expect((float) $this->entry->refresh()->system_qty)->toBe(99.0)
        ->and($this->card->refresh()->status)->toBe(StockCardStatus::Completed)
        ->and($this->card->system_fetched_at)->toBeNull();
    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request['startPeriod'] === today()->toDateString()
        && $request['endPeriod'] === today()->toDateString());
});

it('clears stale transaction columns when refreshing the breakdown fails', function () {
    config()->set('esb.core.base_url', 'https://core-esb.test');
    Cache::put('esb_core.access_token.COM01', 'branch-token', 300);
    Http::fake(['*stock-movement*' => Http::sequence()
        ->push(['status' => 'ok', 'result' => ['data' => [[
            'branchCode' => 'TST01', 'productCode' => 'MAT-001', 'UOM' => 'KG',
            'transactionType' => 'Goods Receipt', 'qtyIn' => 5, 'qtyBalance' => 5,
        ]], 'next' => '', 'count' => 1]])
        ->push(['status' => 'fail', 'message' => 'Unavailable'], 503),
    ]);
    actingSupervisor($this->branch);
    $page = Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('loadTransactionBreakdown')
        ->assertSet('transactionsLoaded', true);

    $page->call('loadTransactionBreakdown')
        ->assertSet('transactionsLoaded', false)
        ->assertSet('transactionTypes', [])
        ->assertSet('transactionQuantities', [])
        ->assertSee('Unavailable');
});

it('refreshes ESB quantities and transaction snapshots only from the detail', function () {
    config()->set('esb.core.base_url', 'https://core-esb.test');
    Cache::put('esb_core.access_token.COM01', 'branch-token', 300);
    Http::preventStrayRequests();
    Http::fake(['*stock-movement*' => Http::response(['status' => 'ok', 'result' => [
        'data' => [[
            'branchCode' => 'TST01', 'productCode' => 'MAT-001', 'UOM' => 'KG', 'location' => 'Kitchen',
            'documentDate' => today()->toDateString(), 'transactionType' => 'Goods Receipt',
            'qtyIn' => 7, 'qtyOut' => 0, 'qtyBalance' => 7,
        ]], 'next' => '', 'count' => 1,
    ]])]);
    actingSupervisor($this->branch);

    Livewire::test(ListStockCards::class)
        ->assertTableActionDoesNotExist('refreshEsb')
        ->assertDontSee('Browse Stock Movement ESB');
    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('refetchEsb')
        ->assertNotified('Data sistem berhasil diperbarui dari ESB')
        ->assertSee('Qty Utama')
        ->assertSee('Goods Receipt')
        ->assertSee('Qty Staff')
        ->assertSeeHtml('wire:model="entryRows.'.$this->entry->id.'.actual_qty"')
        ->assertSee('Masuk 7');

    expect((float) $this->entry->refresh()->system_qty)->toBe(7.0)
        ->and($this->card->refresh()->movement_snapshot['types'])->toBe(['Beginning', 'Goods Delivery', 'Goods Receipt', 'POS Sales', 'Purchase Invoice Adjustment'])
        ->and($this->card->movement_snapshot['transactions']['MAT-001']['Goods Receipt']['qty_in'])->toEqual(7)
        ->and($this->card->system_fetched_at)->not->toBeNull()
        ->and($this->card->status)->toBe(StockCardStatus::PendingSupervisor)
        ->and($this->card->approvals()->count())->toBe(0);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->assertSet('transactionTypes', ['Beginning', 'Goods Delivery', 'Goods Receipt', 'POS Sales', 'Purchase Invoice Adjustment'])
        ->assertSee('Masuk 7');
});

it('blocks detail system refresh for users without review permission', function () {
    $viewer = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $viewer->givePermissionTo('view stock cards');
    actingAs($viewer);

    Livewire::test(ListStockCards::class)->assertTableActionDoesNotExist('refreshEsb');
    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('refetchEsb')->assertForbidden();
});

it('shows paginated ESB products even when the draft has no saved entries', function () {
    $this->card->update(['status' => StockCardStatus::Draft->value]);
    $this->card->entries()->delete();
    config()->set('esb.core.base_url', 'https://core-esb.test');
    Cache::put('esb_core.access_token.COM01', 'branch-token', 300);
    Http::preventStrayRequests();
    $rows = collect(range(1, 26))->map(fn (int $number): array => [
        'branchCode' => 'TST01', 'productCode' => sprintf('ESB-%02d', $number),
        'productName' => sprintf('Produk ESB %02d', $number), 'UOM' => 'KG', 'location' => 'Kitchen',
        'documentDate' => today()->toDateString(), 'transactionType' => 'Beginning',
        'qtyIn' => $number, 'qtyOut' => 0, 'qtyBalance' => $number,
    ])->all();
    Http::fake(['*stock-movement*' => Http::response(['status' => 'ok', 'result' => [
        'data' => $rows, 'next' => '', 'count' => count($rows),
    ]])]);
    actingSupervisor($this->branch);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('loadTransactionBreakdown')
        ->assertSee('Stock Movement ESB')
        ->assertDontSee('Beginning')
        ->assertSee('Produk ESB 01')
        ->assertDontSee('Produk ESB 26')
        ->assertSee('Menampilkan 1–25 dari 26 produk ESB')
        ->call('goToMovementPage', 2)
        ->assertSee('Produk ESB 26')
        ->assertDontSee('Produk ESB 01')
        ->set('movementSearch', 'ESB-26')
        ->assertSet('movementPage', 1)
        ->assertSee('Produk ESB 26')
        ->assertSee('Menampilkan 1–1 dari 1 produk ESB');

    expect($this->card->entries()->count())->toBe(0)
        ->and($this->card->refresh()->status)->toBe(StockCardStatus::Draft)
        ->and($this->card->system_fetched_at)->toBeNull();
    Http::assertSentCount(1);
});

it('keeps the index report only and browses all ESB products in detail without saved entries', function () {
    config()->set('esb.core.base_url', 'https://core-esb.test');
    Cache::put('esb_core.access_token.COM01', 'branch-token', 300);
    Http::preventStrayRequests();
    $this->card->entries()->delete();
    $rows = collect(range(1, 26))->map(fn (int $number): array => [
        'branchCode' => 'TST01', 'productCode' => sprintf('ALL-%02d', $number),
        'productName' => sprintf('All Product %02d', $number), 'UOM' => 'KG', 'location' => 'Kitchen',
        'documentDate' => today()->toDateString(), 'transactionType' => $number === 26 ? 'Special API Type' : 'POS Sales',
        'qtyIn' => 0, 'qtyOut' => 2, 'qtyBalance' => 8,
    ])->all();
    Http::fake(['*stock-movement*' => Http::response(['status' => 'ok', 'result' => [
        'data' => $rows, 'next' => '', 'count' => 26,
    ]])]);
    actingSupervisor($this->branch);

    Livewire::test(ListStockCards::class)->assertDontSee('Browse Stock Movement ESB')->assertDontSee('Refresh Rincian Transaksi');
    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('loadTransactionBreakdown')
        ->assertSee('Stock Movement ESB')
        ->assertSee('All Product 01')
        ->assertSee('POS Sales')
        ->assertSee('Special API Type')
        ->assertSee('Keluar 2')
        ->assertDontSee('All Product 26')
        ->call('goToMovementPage', 2)
        ->assertSee('All Product 26')
        ->assertSee('Qty Staff');

    expect($this->card->entries()->count())->toBe(0);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request['branchCode'] === 'TST01' && $request['startPeriod'] === today()->toDateString());
});

it('blocks detail movement browsing outside the users accessible branches', function () {
    $otherBranch = Branch::factory()->create();
    $otherBranch->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'OTHER']);
    $viewer = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $viewer->givePermissionTo('view stock cards');
    actingAs($viewer);
    Http::preventStrayRequests();
    Http::fake();

    $otherCard = StockCard::factory()->create(['branch_id' => $otherBranch->id]);
    Livewire::test(ViewStockCard::class, ['record' => $otherCard])->assertForbidden();

    Http::assertNothingSent();
});

it('loads all movements for the detail report date', function () {
    fakeEsbStockMovement();
    actingSupervisor($this->branch);
    $date = today()->subDay()->toDateString();

    $this->card->update(['report_date' => $date]);
    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('loadTransactionBreakdown')
        ->assertSet('transactionsLoaded', true)
        ->assertSee('Ayam');

    Http::assertSent(fn ($request): bool => $request['startPeriod'] === $date && $request['endPeriod'] === $date
        && $request['branchCode'] === 'TST01');
});

it('hides beginning and invoice adjustment columns while preserving their data and main balance', function () {
    config()->set('esb.core.base_url', 'https://core-esb.test');
    Cache::put('esb_core.access_token.COM01', 'branch-token', 300);
    Http::fake(['*stock-movement*' => Http::response(['status' => 'ok', 'result' => [
        'data' => [[
            'branchCode' => 'TST01', 'productCode' => 'MAT-001', 'productName' => 'Ayam', 'UOM' => 'KG',
            'location' => 'Kitchen', 'documentDate' => today()->toDateString(),
            'transactionType' => 'Beginning', 'qtyIn' => 5, 'qtyOut' => 0, 'qtyBalance' => 5,
        ]], 'count' => 1, 'next' => '',
    ]])]);
    actingSupervisor($this->branch);

    Livewire::test(ViewStockCard::class, ['record' => $this->card])
        ->call('loadTransactionBreakdown')
        ->assertSet('transactionTypes', ['Beginning', 'Goods Delivery', 'Goods Receipt', 'POS Sales', 'Purchase Invoice Adjustment'])
        ->assertDontSee('Beginning')->assertSee('Goods Delivery')->assertSee('Goods Receipt')
        ->assertSee('POS Sales')->assertDontSee('Purchase Invoice Adjustment')
        ->assertSee('3 tipe transaksi')
        ->assertSet('movementBalances.0.totalQty', 5.0)
        ->assertSet('transactionQuantities.MAT-001.Beginning.qty_in', 5.0)
        ->assertSee('Masuk 0')->assertSee('Keluar 0')
        ->assertDontSee('Masuk 5');
});
