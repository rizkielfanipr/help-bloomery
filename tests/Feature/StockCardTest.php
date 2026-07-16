<?php

use App\Filament\Casual\Pages\StockCardEntryPage;
use App\Filament\Casual\Pages\StockCardPage;
use App\Filament\Helpdesk\Resources\StockCards\Pages\ListStockCards;
use App\Filament\Helpdesk\Resources\StockCards\Pages\ViewStockCard;
use App\Models\Branch;
use App\Models\StockCard;
use App\Models\StockCardEntry;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create([
        'esb_branch_code' => 'TST01',
        'esb_comcode' => 'COM01',
    ]);

    $this->storeUser = User::factory()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);
    $this->storeUser->assignRole('STORE_STAFF');
});

// ── Casual panel ─────────────────────────────────────────────────────────────

it('shows stock card index page for store staff', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardPage::class)
        ->assertOk()
        ->assertSet('reportDate', now()->toDateString());
});

it('returns null status when no stock card exists', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    $status = Livewire::test(StockCardPage::class)->instance()->getDailyStatus();

    expect($status)->toBeNull();
});

it('returns submitted_at when stock card is submitted', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    StockCard::factory()->submitted()->create([
        'branch_id' => $this->branch->id,
        'report_date' => now()->toDateString(),
        'submitted_by' => $this->storeUser->id,
    ]);

    $status = Livewire::test(StockCardPage::class)->instance()->getDailyStatus();

    expect($status)->not->toBeNull();
});

it('blocks requestConfirm when ESB not fetched', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
        ->call('requestConfirm')
        ->assertNotified();
});

it('blocks save when a row has empty actual_qty', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
        ->set('esbFetched', true)
        ->set('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Ayam', 'system_qty' => 5.0, 'system_unit' => 'KG', 'actual_qty' => '', 'notes' => ''],
        ])
        ->call('requestConfirm')
        ->assertNotified();
});

it('blocks save when variance exists but notes is empty', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
        ->set('esbFetched', true)
        ->set('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Ayam', 'system_qty' => 5.0, 'system_unit' => 'KG', 'actual_qty' => '4', 'notes' => ''],
        ])
        ->call('requestConfirm')
        ->assertNotified();
});

it('saves stock card with entries and marks as submitted', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
        ->set('esbFetched', true)
        ->set('flagUnit', 'stockUnit')
        ->set('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Ayam', 'system_qty' => 5.0, 'system_unit' => 'KG', 'actual_qty' => '5', 'notes' => ''],
            ['product_code' => 'MAT-002', 'product_name' => 'Beras', 'system_qty' => 10.0, 'system_unit' => 'KG', 'actual_qty' => '9', 'notes' => 'Tercecer saat pindah'],
        ])
        ->call('requestConfirm')
        ->call('save')
        ->assertSet('isSubmitted', true);

    expect(StockCard::where('branch_id', $this->branch->id)->whereDate('report_date', now())->exists())->toBeTrue();
    expect(StockCardEntry::count())->toBe(2);
});

it('calculates variance correctly', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    $variance = Livewire::test(StockCardEntryPage::class)
        ->set('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Ayam', 'system_qty' => 5.0, 'system_unit' => 'KG', 'actual_qty' => '3', 'notes' => ''],
        ])
        ->instance()
        ->getVariance(0);

    expect($variance)->toBe(-2.0);
});

it('returns null variance when actual_qty is empty', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    $variance = Livewire::test(StockCardEntryPage::class)
        ->set('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Ayam', 'system_qty' => 5.0, 'system_unit' => 'KG', 'actual_qty' => '', 'notes' => ''],
        ])
        ->instance()
        ->getVariance(0);

    expect($variance)->toBeNull();
});

// ── Helpdesk panel ───────────────────────────────────────────────────────────

it('allows admin to list stock cards', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $admin = User::factory()->create(['is_active' => true]);
    $admin->givePermissionTo('view stock cards');
    actingAs($admin);

    StockCard::factory()->submitted()->create(['branch_id' => $this->branch->id]);

    Livewire::test(ListStockCards::class)->assertOk();
});

it('allows admin to view stock card detail with entries', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $admin = User::factory()->create(['is_active' => true]);
    $admin->givePermissionTo('view stock cards');
    actingAs($admin);

    $card = StockCard::factory()->submitted()->create(['branch_id' => $this->branch->id]);
    StockCardEntry::factory()->create([
        'stock_card_id' => $card->id,
        'actual_qty' => 4.5,
        'notes' => 'Defisit kecil',
    ]);

    Livewire::test(ViewStockCard::class, ['record' => $card])->assertOk();
});
