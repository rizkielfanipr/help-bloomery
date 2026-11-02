<?php

use App\Actions\Inventory\StockCard\PrepareStockCardDraftAction;
use App\Actions\Inventory\StockCard\SaveStockCardDraftAction;
use App\Actions\Inventory\StockCard\SubmitStockCardAction;
use App\Enums\StockCardStatus;
use App\Filament\Casual\Pages\StockCardCalendarPage;
use App\Filament\Casual\Pages\StockCardEntryPage;
use App\Filament\Casual\Pages\StockCardHistoryPage;
use App\Filament\Casual\Pages\StockCardPage;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\StockCard;
use App\Models\StockCardApproval;
use App\Models\StockCardEntry;
use App\Models\User;
use App\Services\EsbStockMovementService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));

    $this->branch = Branch::factory()->create();
    $this->otherBranch = Branch::factory()->create();
    $this->staff = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $this->staff->assignRole('STORE_STAFF');
    $this->employee = Employee::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $this->date = now()->toDateString();
    $this->actingAs($this->staff);
});

function draftWithTwoProducts(object $test, array $firstEntry = [], array $secondEntry = [], array $employeeIds = []): StockCard
{
    return seedStockCardDraft($test->branch, [
        ['product_code' => 'A-1', 'product_name' => 'Ayam', ...$firstEntry],
        ['product_code' => 'B-1', 'product_name' => 'Beras', ...$secondEntry],
    ], $employeeIds);
}

function storedEntry(string $productCode): StockCardEntry
{
    return StockCardEntry::query()->where('product_code', $productCode)->firstOrFail();
}

it('saves a partial draft and restores it after a reload', function () {
    $card = draftWithTwoProducts($this);

    Livewire::test(StockCardEntryPage::class)
        ->set('rows.0.actual_qty', '5.25')
        ->set('rows.0.notes', 'Catatan')
        ->set('employeeIds', [$this->employee->id])
        ->assertSet('draftSaveState', 'saved')
        ->assertSee('Draft · 1/2 item terisi')
        ->assertSee('Draft tersimpan pukul');

    $this->actingAs(User::query()->findOrFail($this->staff->id));

    Livewire::test(StockCardEntryPage::class)
        ->assertSet('rows.0.actual_qty', '5.25')
        ->assertSet('rows.0.notes', 'Catatan')
        ->assertSet('rows.1.actual_qty', '')
        ->assertSet('employeeIds', [$this->employee->id])
        ->assertSet('isSubmitted', false);

    expect($card->fresh()->status)->toBe(StockCardStatus::Draft)
        ->and(StockCardApproval::query()->count())->toBe(0);
});

it('keeps an uncounted qty null and an explicit zero as zero', function (mixed $input, ?string $stored, string $shown) {
    draftWithTwoProducts($this);

    Livewire::test(StockCardEntryPage::class)->set('rows.0.actual_qty', $input);

    expect(storedEntry('A-1')->actual_qty)->toBe($stored);

    Livewire::test(StockCardEntryPage::class)->assertSet('rows.0.actual_qty', $shown);
})->with([
    'empty string' => ['', null, ''],
    'whitespace' => ['   ', null, ''],
    'null' => [null, null, ''],
    'string zero' => ['0', '0.0000', '0'],
    'numeric zero' => [0, '0.0000', '0'],
    'decimal zero' => ['0.0000', '0.0000', '0'],
    'hundred keeps its zeros' => ['100', '100.0000', '100'],
    'four decimals' => ['1.2345', '1.2345', '1.2345'],
    'leading dot' => ['.5', '0.5000', '0.5'],
]);

it('clears a counted qty back to null when the field is emptied', function () {
    draftWithTwoProducts($this, ['actual_qty' => '4']);

    Livewire::test(StockCardEntryPage::class)->set('rows.0.actual_qty', '');

    expect(storedEntry('A-1')->actual_qty)->toBeNull();
});

it('saves a draft without employees or complete quantities and creates no approval', function () {
    $card = draftWithTwoProducts($this, ['actual_qty' => '1']);

    Livewire::test(StockCardEntryPage::class)
        ->call('saveDraft')
        ->assertHasNoErrors()
        ->assertNotified('Draft tersimpan')
        ->assertSet('draftSaveState', 'saved');

    $fresh = $card->fresh();
    expect($fresh->status)->toBe(StockCardStatus::Draft)
        ->and($fresh->submitted_by)->toBeNull()
        ->and($fresh->submitted_at)->toBeNull()
        ->and(storedEntry('B-1')->actual_qty)->toBeNull()
        ->and(storedEntry('A-1')->reported_qty)->toBeNull()
        ->and(StockCardApproval::query()->count())->toBe(0);
});

it('rejects an invalid qty on a draft without losing valid rows', function (mixed $invalid) {
    draftWithTwoProducts($this, ['actual_qty' => '7']);

    Livewire::test(StockCardEntryPage::class)
        ->set('rows.0.actual_qty', $invalid)
        ->assertHasErrors('rows.0.actual_qty')
        ->assertSet('draftSaveState', 'failed')
        ->assertSee('Gagal menyimpan draft')
        ->assertDontSee('Draft tersimpan pukul')
        ->set('rows.1.actual_qty', '3');

    expect(storedEntry('A-1')->actual_qty)->toBe('7.0000')
        ->and(storedEntry('B-1')->actual_qty)->toBe('3.0000');
})->with([
    'negative' => ['-1'],
    'non numeric' => ['abc'],
    'five decimals' => ['1.23456'],
    'scientific' => ['1e3'],
    'too large' => ['12345678901234567'],
]);

it('retries a row that failed once the value is corrected', function () {
    draftWithTwoProducts($this);

    Livewire::test(StockCardEntryPage::class)
        ->set('rows.0.actual_qty', '-1')
        ->assertSet('draftSaveState', 'failed')
        ->set('rows.0.actual_qty', '2')
        ->assertHasNoErrors()
        ->assertSet('draftSaveState', 'saved')
        ->assertSet('dirtyRowIndexes', []);

    expect(storedEntry('A-1')->actual_qty)->toBe('2.0000');
});

it('reports failure and keeps the typed value when the database write fails', function () {
    draftWithTwoProducts($this);
    Event::listen('eloquent.saving: '.StockCardEntry::class, function (): void {
        throw new RuntimeException('database unavailable');
    });

    Livewire::test(StockCardEntryPage::class)
        ->set('rows.0.actual_qty', '5')
        ->assertSet('draftSaveState', 'failed')
        ->assertSet('rows.0.actual_qty', '5')
        ->assertSet('dirtyRowIndexes', [0])
        ->assertSee('Gagal menyimpan draft')
        ->assertSee('Coba lagi')
        ->assertDontSee('Draft tersimpan pukul');

    expect(storedEntry('A-1')->actual_qty)->toBeNull();
});

it('writes only the changed entry on autosave', function () {
    draftWithTwoProducts($this, ['actual_qty' => '1'], ['actual_qty' => '2']);
    $untouchedAt = now()->subDay();
    StockCardEntry::query()->update(['updated_at' => $untouchedAt]);
    DB::table('stock_card_employees')->delete();
    $page = Livewire::test(StockCardEntryPage::class);
    $this->travel(1)->minutes();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $page->set('rows.0.actual_qty', '9');
    $writes = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => preg_match('/^(insert|update|delete)/i', $query) === 1)
        ->filter(fn (string $query): bool => str_contains($query, 'stock_card'));
    DB::disableQueryLog();

    expect($writes)->toHaveCount(2)
        ->and($writes->filter(fn (string $query): bool => str_contains($query, 'stock_card_employees')))->toBeEmpty()
        ->and(storedEntry('B-1')->updated_at->equalTo($untouchedAt->startOfSecond()))->toBeTrue();
});

it('refuses browser edits to product identity or the row list', function (string $property, mixed $value) {
    draftWithTwoProducts($this);

    Livewire::test(StockCardEntryPage::class)
        ->set($property, $value)
        ->assertForbidden();

    expect(StockCardEntry::query()->count())->toBe(2);
})->with([
    'product code' => ['rows.0.product_code', 'B-1'],
    'product name' => ['rows.0.product_name', 'Lain'],
    'whole list' => ['rows', [['product_code' => 'X-1', 'product_name' => 'Palsu', 'system_unit' => 'KG', 'actual_qty' => '1', 'notes' => '']]],
    'new row' => ['rows.5', ['product_code' => 'X-1']],
]);

it('ignores a qty for a row that does not exist', function () {
    draftWithTwoProducts($this);

    Livewire::test(StockCardEntryPage::class)->set('rows.9.actual_qty', '1')->assertSet('dirtyRowIndexes', []);

    expect(StockCardEntry::query()->count())->toBe(2);
});

it('does not save a product that is not on the card', function () {
    draftWithTwoProducts($this);

    expect(fn () => app(SaveStockCardDraftAction::class)->execute($this->staff, $this->date, [
        ['product_code' => 'X-1', 'actual_qty' => '1', 'notes' => null],
    ]))->toThrow(ValidationException::class)
        ->and(StockCardEntry::query()->where('product_code', 'X-1')->exists())->toBeFalse();
});

it('uses the date from mount even if the report date property is changed in the browser', function () {
    $card = draftWithTwoProducts($this);
    $otherDay = seedStockCardDraft($this->branch, [['product_code' => 'A-1']], [], now()->subDay()->toDateString());

    Livewire::test(StockCardEntryPage::class)
        ->set('reportDate', now()->subDay()->toDateString())
        ->set('rows.0.actual_qty', '6');

    expect($card->entries()->where('product_code', 'A-1')->value('actual_qty'))->toEqual('6.0000')
        ->and($otherDay->entries()->value('actual_qty'))->toBeNull();
});

it('submits a complete draft atomically with one approval and a reported snapshot', function () {
    $card = draftWithTwoProducts($this, ['actual_qty' => '5'], ['actual_qty' => '0'], [$this->employee->id]);

    Livewire::test(StockCardEntryPage::class)
        ->call('loadProductCatalog')
        ->call('requestConfirm')
        ->assertSet('showConfirm', true)
        ->assertSee('Koreksi hanya dapat dilakukan melalui Supervisor/Finance')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('isSubmitted', true)
        ->assertSet('status', StockCardStatus::PendingSupervisor)
        ->assertSet('submittedEmployees.0.code', $this->employee->employee_code);

    $fresh = $card->fresh();
    expect($fresh->status)->toBe(StockCardStatus::PendingSupervisor)
        ->and($fresh->submitted_by)->toBe($this->staff->id)
        ->and($fresh->submitted_at)->not->toBeNull()
        ->and(storedEntry('A-1')->reported_qty)->toBe('5.0000')
        ->and(storedEntry('B-1')->reported_qty)->toBe('0.0000')
        ->and(StockCardApproval::query()->where('stock_card_id', $card->id)->where('action', 'submitted')->count())->toBe(1);
});

it('validates a direct submit call that skipped the confirmation', function (array $entry, array $employees, string $errorKey) {
    $card = draftWithTwoProducts($this, $entry, ['actual_qty' => '1'], $employees === ['valid'] ? [$this->employee->id] : []);

    $page = Livewire::test(StockCardEntryPage::class)->call('submit');

    $errorKey === 'employeeIds' ? $page->assertHasErrors('employeeIds') : $page->assertNotified('Stock Card belum lengkap');

    expect($card->fresh()->status)->toBe(StockCardStatus::Draft)
        ->and(StockCardApproval::query()->count())->toBe(0)
        ->and(storedEntry('B-1')->reported_qty)->toBeNull();
})->with([
    'empty qty' => [[], ['valid'], 'rows'],
    'no employee' => [['actual_qty' => '1'], [], 'employeeIds'],
]);

it('rejects a submit whose stored qty is invalid', function () {
    $card = draftWithTwoProducts($this, ['actual_qty' => '-3'], ['actual_qty' => '1'], [$this->employee->id]);

    expect(fn () => app(SubmitStockCardAction::class)->execute($this->staff, $this->date))
        ->toThrow(ValidationException::class)
        ->and($card->fresh()->status)->toBe(StockCardStatus::Draft);
});

it('rejects invalid values passed directly to the save action', function (mixed $invalid) {
    draftWithTwoProducts($this);

    expect(fn () => app(SaveStockCardDraftAction::class)->execute($this->staff, $this->date, [
        ['product_code' => 'A-1', 'actual_qty' => $invalid, 'notes' => null],
    ]))->toThrow(ValidationException::class)
        ->and(storedEntry('A-1')->actual_qty)->toBeNull();
})->with(['negative' => ['-1'], 'non numeric' => ['abc'], 'five decimals' => ['1.23456'], 'array' => [['1']]]);

it('rejects employees that are inactive or from another branch on save and submit', function (string $kind) {
    $invalid = Employee::factory()->create($kind === 'inactive'
        ? ['branch_id' => $this->branch->id, 'is_active' => false]
        : ['branch_id' => $this->otherBranch->id, 'is_active' => true]);
    $card = draftWithTwoProducts($this, ['actual_qty' => '1'], ['actual_qty' => '1'], [$invalid->id]);

    Livewire::test(StockCardEntryPage::class)
        ->set('employeeIds', [$invalid->id])
        ->assertHasErrors('employeeIds.0');

    expect(fn () => app(SubmitStockCardAction::class)->execute($this->staff, $this->date))
        ->toThrow(ValidationException::class)
        ->and($card->fresh()->status)->toBe(StockCardStatus::Draft);
})->with(['inactive', 'another branch']);

it('replaces a stored employee who was deactivated so the draft can still be sent', function () {
    $former = Employee::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $card = draftWithTwoProducts($this, ['actual_qty' => '1'], ['actual_qty' => '1'], [$former->id]);
    $former->update(['is_active' => false]);

    Livewire::test(StockCardEntryPage::class)
        ->assertSet('employeeIds', [])
        ->set('employeeIds', [$this->employee->id])
        ->call('requestConfirm')
        ->call('submit')
        ->assertSet('isSubmitted', true);

    expect($card->employees()->pluck('employee_id')->all())->toBe([$this->employee->id]);
});

it('does not double the approval or transition when submitted twice', function () {
    $card = draftWithTwoProducts($this, ['actual_qty' => '5'], ['actual_qty' => '1'], [$this->employee->id]);
    $action = app(SubmitStockCardAction::class);

    $action->execute($this->staff, $this->date);
    $submittedAt = $card->fresh()->submitted_at;
    $this->travel(5)->minutes();

    expect(fn () => $action->execute($this->staff, $this->date))->toThrow(ValidationException::class)
        ->and(StockCardApproval::query()->count())->toBe(1)
        ->and($card->fresh()->submitted_at->equalTo($submittedAt))->toBeTrue();
});

it('shows a stale page the locked report when another device already submitted', function () {
    $card = draftWithTwoProducts($this, ['actual_qty' => '5'], ['actual_qty' => '1'], [$this->employee->id]);
    $page = Livewire::test(StockCardEntryPage::class);

    app(SubmitStockCardAction::class)->execute($this->staff, $this->date);

    $page->call('submit')
        ->assertSet('isSubmitted', true)
        ->assertNotified('Stock Card sudah dikirim dan tidak dapat diubah.');

    expect(StockCardApproval::query()->count())->toBe(1);

    $stale = Livewire::test(StockCardEntryPage::class);
    $stale->assertSet('isSubmitted', true);
    expect($card->fresh()->status)->toBe(StockCardStatus::PendingSupervisor);
});

it('rejects a late autosave on a stale page once the report is sent', function () {
    draftWithTwoProducts($this, ['actual_qty' => '5'], ['actual_qty' => '1'], [$this->employee->id]);
    $page = Livewire::test(StockCardEntryPage::class);

    app(SubmitStockCardAction::class)->execute($this->staff, $this->date);

    $page->set('rows.0.actual_qty', '99')
        ->assertSet('isSubmitted', true)
        ->assertSet('rows.0.actual_qty', '5')
        ->assertNotified('Stock Card sudah dikirim dan tidak dapat diubah.');

    expect(storedEntry('A-1')->actual_qty)->toBe('5.0000')
        ->and(storedEntry('A-1')->reported_qty)->toBe('5.0000');
});

it('rolls every change back when the submit fails midway', function (string $failingModel) {
    $card = draftWithTwoProducts($this, ['actual_qty' => '5'], ['actual_qty' => '1'], [$this->employee->id]);
    $before = $card->fresh()->only(['status', 'submitted_by', 'submitted_at']);
    Event::listen('eloquent.saving: '.$failingModel, function (): void {
        throw new RuntimeException('failure during submit');
    });

    Livewire::test(StockCardEntryPage::class)
        ->call('submit')
        ->assertNotified('Stock Card gagal dikirim')
        ->assertSet('isSubmitted', false);

    expect($card->fresh()->only(['status', 'submitted_by', 'submitted_at']))->toEqual($before)
        ->and(storedEntry('A-1')->reported_qty)->toBeNull()
        ->and(StockCardApproval::query()->count())->toBe(0);
})->with([
    'approval' => [StockCardApproval::class],
    'card status' => [StockCard::class],
]);

it('takes a row lock and runs inside a transaction when submitting', function () {
    draftWithTwoProducts($this, ['actual_qty' => '5'], ['actual_qty' => '1'], [$this->employee->id]);
    $levelDuringStatusUpdate = null;
    Event::listen('eloquent.saving: '.StockCard::class, function () use (&$levelDuringStatusUpdate): void {
        $levelDuringStatusUpdate = DB::transactionLevel();
    });
    $baseline = DB::transactionLevel();

    app(SubmitStockCardAction::class)->execute($this->staff, $this->date);

    expect($levelDuringStatusUpdate)->toBe($baseline + 1)
        ->and(file_get_contents(app_path('Actions/Inventory/StockCard/SubmitStockCardAction.php')))->toContain('->lockForUpdate()');
});

it('locks the draft for staff after it is sent', function (StockCardStatus $status) {
    $card = draftWithTwoProducts($this, ['actual_qty' => '5'], ['actual_qty' => '1'], [$this->employee->id]);
    $card->update(['status' => $status->value]);

    Livewire::test(StockCardEntryPage::class)
        ->assertSet('isSubmitted', true)
        ->assertDontSee('Simpan Draft')
        ->assertDontSee('Kirim untuk Review')
        ->assertSee($status->getLabel());

    expect(fn () => app(SaveStockCardDraftAction::class)->execute($this->staff, $this->date, [
        ['product_code' => 'A-1', 'actual_qty' => '9', 'notes' => null],
    ]))->toThrow(ValidationException::class)
        ->and(storedEntry('A-1')->actual_qty)->toBe('5.0000');
})->with([StockCardStatus::PendingSupervisor, StockCardStatus::PendingFinance, StockCardStatus::Completed]);

it('only lets a user with edit permission on the same branch change or send a draft', function () {
    $card = draftWithTwoProducts($this, ['actual_qty' => '5'], ['actual_qty' => '1'], [$this->employee->id]);
    $noPermission = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $noPermission->givePermissionTo('access employee app stock card');
    $foreign = User::factory()->create(['branch_id' => $this->otherBranch->id, 'is_active' => true]);
    $foreign->assignRole('STORE_STAFF');
    $inactive = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => false]);
    $inactive->assignRole('STORE_STAFF');
    $save = app(SaveStockCardDraftAction::class);
    $submit = app(SubmitStockCardAction::class);

    expect(fn () => $save->execute($noPermission, $this->date, [['product_code' => 'A-1', 'actual_qty' => '9', 'notes' => null]]))->toThrow(AuthorizationException::class)
        ->and(fn () => $submit->execute($noPermission, $this->date))->toThrow(AuthorizationException::class)
        ->and(fn () => $save->execute($inactive, $this->date, [['product_code' => 'A-1', 'actual_qty' => '9', 'notes' => null]]))->toThrow(AuthorizationException::class)
        ->and(fn () => $submit->execute($inactive, $this->date))->toThrow(AuthorizationException::class)
        ->and(fn () => $save->execute($foreign, $this->date, [['product_code' => 'A-1', 'actual_qty' => '9', 'notes' => null]]))->toThrow(ValidationException::class)
        ->and(fn () => $submit->execute($foreign, $this->date))->toThrow(ValidationException::class)
        ->and(storedEntry('A-1')->actual_qty)->toBe('5.0000')
        ->and($card->fresh()->status)->toBe(StockCardStatus::Draft);
});

it('shows a permission message instead of a false success on the page', function () {
    draftWithTwoProducts($this);
    $noPermission = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $noPermission->givePermissionTo('access employee app stock card');
    $this->actingAs($noPermission);

    Livewire::test(StockCardEntryPage::class)
        ->set('rows.0.actual_qty', '5')
        ->assertSet('draftSaveState', 'failed')
        ->assertNotified('Anda tidak memiliki izin mengubah Stock Card ini')
        ->call('submit')
        ->assertNotified();

    expect(storedEntry('A-1')->actual_qty)->toBeNull();
});

it('requires the create permission to prepare a draft', function () {
    $noPermission = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $noPermission->givePermissionTo(['access employee app stock card', 'edit stock cards']);

    expect(fn () => app(PrepareStockCardDraftAction::class)->execute($noPermission, $this->date, 'stockUnit', [], [], []))
        ->toThrow(AuthorizationException::class)
        ->and(StockCard::query()->count())->toBe(0);
});

it('covers the draft rules in the policy', function () {
    $user = $this->staff;
    $draft = StockCard::factory()->create(['branch_id' => $this->branch->id, 'status' => StockCardStatus::Draft->value]);
    $sent = StockCard::factory()->submitted()->create(['branch_id' => $this->branch->id, 'report_date' => now()->subDay()]);
    $foreign = StockCard::factory()->create(['branch_id' => $this->otherBranch->id, 'status' => StockCardStatus::Draft->value]);
    $allBranches = User::factory()->create(['access_all_branches' => true, 'is_active' => true]);
    $allBranches->assignRole('STORE_STAFF');

    expect($user->can('createDraft', StockCard::class))->toBeTrue()
        ->and($user->can('editDraft', $draft))->toBeTrue()
        ->and($user->can('submit', $draft))->toBeTrue()
        ->and($user->can('editDraft', $sent))->toBeFalse()
        ->and($user->can('submit', $sent))->toBeFalse()
        ->and($user->can('editDraft', $foreign))->toBeFalse()
        ->and($allBranches->can('submit', $foreign))->toBeFalse();
});

it('prepares a draft with no fake quantity and keeps existing entries when another device already did', function () {
    $prepare = app(PrepareStockCardDraftAction::class);
    $products = [['product_code' => 'A-1', 'product_name' => 'Ayam', 'product_category' => 'WIP', 'system_unit' => 'KG']];

    $card = $prepare->execute($this->staff, $this->date, 'stockUnit', $products, ['rule' => 1], ['generated_at' => 'first']);

    expect($card->status)->toBe(StockCardStatus::Draft)
        ->and(storedEntry('A-1')->actual_qty)->toBeNull()
        ->and($card->fresh()->category_settings_snapshot)->toBe(['rule' => 1]);

    StockCardEntry::query()->update(['actual_qty' => 4]);

    $prepare->execute($this->staff, $this->date, 'stockUnit', [
        ['product_code' => 'Z-9', 'product_name' => 'Baru', 'product_category' => 'WIP', 'system_unit' => 'KG'],
    ], ['rule' => 2], ['generated_at' => 'second']);

    expect(StockCard::query()->count())->toBe(1)
        ->and(StockCardEntry::query()->pluck('product_code')->all())->toBe(['A-1'])
        ->and(storedEntry('A-1')->actual_qty)->toBe('4.0000')
        ->and($card->fresh()->category_settings_snapshot)->toBe(['rule' => 1])
        ->and($card->fresh()->selection_snapshot)->toBe(['generated_at' => 'first']);
});

it('keeps snapshots and a filled legacy row while saving and submitting', function () {
    Http::fake();
    $card = draftWithTwoProducts($this, ['actual_qty' => '5'], ['actual_qty' => '1'], [$this->employee->id]);
    $card->update(['category_settings_snapshot' => ['rule' => 'x'], 'selection_snapshot' => ['generated_at' => 'kept']]);
    $legacy = StockCardEntry::factory()->create(['stock_card_id' => $card->id, 'product_code' => 'OLD-1', 'actual_qty' => '3', 'is_manual' => true]);

    Livewire::test(StockCardEntryPage::class)
        ->assertSet('selectionSnapshot', ['generated_at' => 'kept'])
        ->set('rows.0.actual_qty', '8')
        ->call('saveDraft')
        ->call('requestConfirm')
        ->call('submit')
        ->assertSet('isSubmitted', true);

    $fresh = $card->fresh();
    expect($legacy->fresh()->actual_qty)->toBe('3.0000')
        ->and($legacy->fresh()->reported_qty)->toBe('3.0000')
        ->and($fresh->category_settings_snapshot)->toBe(['rule' => 'x'])
        ->and($fresh->selection_snapshot)->toBe(['generated_at' => 'kept']);
    Http::assertNothingSent();
});

it('resumes a draft without calling ESB', function () {
    Http::fake();
    draftWithTwoProducts($this, ['actual_qty' => '5']);

    Livewire::test(StockCardEntryPage::class)
        ->call('loadProductCatalog')
        ->assertSet('catalogLoaded', true)
        ->assertSet('catalogError', null);

    Http::assertNothingSent();
});

it('does not rebuild a filled draft from a changed catalog', function () {
    $mapping = $this->branch->esbCodes()->create(['esb_branch_code' => 'TST01', 'esb_comcode' => 'COM01']);
    $this->branch->update(['stock_card_esb_code_id' => $mapping->id]);
    draftWithTwoProducts($this, ['actual_qty' => '5']);
    app(EsbStockMovementService::class)->cacheStockCardCatalog($this->branch, now(), 'stockUnit', [
        'products' => [['product_code' => 'NEW-1', 'product_name' => 'Baru', 'category' => 'WIP', 'unit' => 'KG', 'usage_days' => 1, 'total_qty' => 1.0]],
        'period_from' => $this->date,
        'period_to' => $this->date,
        'failed_requests' => 0,
    ]);

    Livewire::test(StockCardEntryPage::class)
        ->call('loadProductCatalog')
        ->assertSet('catalogLoaded', true)
        ->assertCount('rows', 2)
        ->set('rows.1.actual_qty', '2');

    expect(StockCardEntry::query()->pluck('product_code')->sort()->values()->all())->toBe(['A-1', 'B-1'])
        ->and(storedEntry('A-1')->actual_qty)->toBe('5.0000');
});

it('applies an employee ticked before the product list finished loading', function () {
    $mapping = $this->branch->esbCodes()->create(['esb_branch_code' => 'TST01', 'esb_comcode' => 'COM01']);
    $this->branch->update(['stock_card_esb_code_id' => $mapping->id]);
    app(EsbStockMovementService::class)->cacheStockCardCatalog($this->branch, now(), 'stockUnit', [
        'products' => [['product_code' => 'NEW-1', 'product_name' => 'Baru', 'category' => 'WIP', 'unit' => 'KG', 'usage_days' => 1, 'total_qty' => 1.0]],
        'period_from' => $this->date,
        'period_to' => $this->date,
        'failed_requests' => 0,
    ]);

    Livewire::test(StockCardEntryPage::class)
        ->set('employeeIds', [$this->employee->id])
        ->call('loadProductCatalog')
        ->assertSet('employeeIds', [$this->employee->id]);

    expect(StockCard::query()->firstOrFail()->employees()->pluck('employee_id')->all())->toBe([$this->employee->id])
        ->and(storedEntry('NEW-1')->actual_qty)->toBeNull();
});

it('shows each status and action on the landing page without loading entries', function () {
    $page = fn () => Livewire::test(StockCardPage::class);

    $page()->assertSee('Belum dimulai')->assertSee('Mulai isi')->assertDontSee('Belum diisi');

    $card = draftWithTwoProducts($this, ['actual_qty' => '0']);
    $page()->assertSee('Draft · 1/2 item terisi')->assertSee('Lanjutkan Draft')->assertDontSee('Belum diisi');

    foreach ([
        [StockCardStatus::PendingSupervisor, 'Menunggu review Supervisor'],
        [StockCardStatus::PendingFinance, 'Menunggu review Finance'],
        [StockCardStatus::Completed, 'Selesai'],
    ] as [$status, $label]) {
        $card->update(['status' => $status->value]);
        $page()->assertSee($label)->assertSee('Lihat')->assertDontSee('Lanjutkan Draft');
    }

    $status = $page()->instance()->getDailyStatus();
    expect($status->relationLoaded('entries'))->toBeFalse()
        ->and($status->entries_count)->toBe(2)
        ->and($status->filled_entries_count)->toBe(1);
});

it('labels the entry actions', function () {
    draftWithTwoProducts($this);

    Livewire::test(StockCardEntryPage::class)
        ->call('loadProductCatalog')
        ->assertSee('Simpan Draft')
        ->assertSee('Kirim untuk Review')
        ->assertDontSee('Simpan Stock Card')
        ->assertSeeHtml('placeholder="0"')
        ->assertSee('Menyimpan draft…');
});

it('marks drafts separately on the calendar and keeps them out of the submitted count', function () {
    $this->travelTo(now()->startOfMonth()->addDays(5));
    seedStockCardDraft($this->branch, [['product_code' => 'A-1']], [], now()->subDays(2)->toDateString());
    StockCard::factory()->submitted()->create(['branch_id' => $this->branch->id, 'report_date' => now()->subDays(1)->toDateString()]);

    $page = Livewire::test(StockCardCalendarPage::class);
    $calendar = $page->instance()->calendarData();
    $day = fn (int $daysAgo): array => collect($calendar['days'])->firstWhere('date', now()->subDays($daysAgo)->toDateString());

    expect($calendar['submittedCount'])->toBe(1)
        ->and($calendar['draftCount'])->toBe(1)
        ->and($calendar['missedCount'])->toBe($calendar['pastDaysCount'] - 2)
        ->and($day(2)['isDraft'])->toBeTrue()
        ->and($day(2)['isSubmitted'])->toBeFalse()
        ->and($day(1)['isDraft'])->toBeFalse();

    $page->assertSeeHtml('data-stock-card-draft="'.now()->subDays(2)->toDateString().'"')
        ->assertSee('Draft belum dikirim');
});

it('tells a user without the create permission why the draft cannot start', function () {
    $mapping = $this->branch->esbCodes()->create(['esb_branch_code' => 'TST01', 'esb_comcode' => 'COM01']);
    $this->branch->update(['stock_card_esb_code_id' => $mapping->id]);
    app(EsbStockMovementService::class)->cacheStockCardCatalog($this->branch, now(), 'stockUnit', [
        'products' => [['product_code' => 'NEW-1', 'product_name' => 'Baru', 'category' => 'WIP', 'unit' => 'KG', 'usage_days' => 1, 'total_qty' => 1.0]],
        'period_from' => $this->date,
        'period_to' => $this->date,
        'failed_requests' => 0,
    ]);
    $viewer = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $viewer->givePermissionTo('access employee app stock card');
    $this->actingAs($viewer);

    Livewire::test(StockCardEntryPage::class)
        ->call('loadProductCatalog')
        ->assertSet('catalogError', 'Akun Anda tidak memiliki izin membuat Stock Card.')
        ->assertSet('rows', []);

    expect(StockCard::query()->count())->toBe(0);
});

it('keeps drafts out of history', function () {
    $draft = seedStockCardDraft($this->branch, [['product_code' => 'DRAFT-ONLY', 'actual_qty' => '1']]);
    $submitted = StockCard::factory()->submitted()->create(['branch_id' => $this->branch->id, 'report_date' => now()->subDay()->toDateString(), 'submitted_by' => $this->staff->id]);

    $historyIds = Livewire::test(StockCardHistoryPage::class)->instance()->stockCards()->pluck('id');

    expect($historyIds->all())->toBe([$submitted->id])
        ->and($historyIds)->not->toContain($draft->id);
});
