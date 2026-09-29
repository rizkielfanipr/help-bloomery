<?php

use App\Enums\RndBomChangeLogSource;
use App\Enums\RndBomChangeLogStatus;
use App\Filament\Helpdesk\Pages\BomAdjustmentPage;
use App\Filament\Helpdesk\Pages\EditBomAdjustmentPage;
use App\Models\RndBomCatalog;
use App\Models\RndBomChangeLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->viewer = User::factory()->create(['is_active' => true]);
    $this->viewer->givePermissionTo(['access backoffice', 'view bill of materials']);

    $this->editor = User::factory()->create(['is_active' => true]);
    $this->editor->givePermissionTo(['access backoffice', 'view bill of materials', 'edit bill of materials']);

    $this->historyViewer = User::factory()->create(['is_active' => true]);
    $this->historyViewer->givePermissionTo(['access backoffice', 'view bill of materials', 'view bom adjustment history']);

    $this->reconciler = User::factory()->create(['is_active' => true]);
    $this->reconciler->givePermissionTo(['access backoffice', 'view bill of materials', 'view bom adjustment history', 'reconcile bom adjustments']);
});

it('blocks access to both pages without view bill of materials', function () {
    $outsider = User::factory()->create(['is_active' => true]);
    $this->actingAs($outsider);

    expect(BomAdjustmentPage::canAccess())->toBeFalse()
        ->and(EditBomAdjustmentPage::canAccess())->toBeFalse();
});

it('allows access to the index for viewers but only allows the editor to edit', function () {
    $this->actingAs($this->viewer);
    expect(BomAdjustmentPage::canAccess())->toBeTrue()
        ->and(EditBomAdjustmentPage::canAccess())->toBeFalse();

    $this->actingAs($this->editor);
    expect(EditBomAdjustmentPage::canAccess())->toBeTrue();
});

it('renders the BOM Adjustment link in the custom helpdesk sidebar for a permitted user', function () {
    $this->actingAs($this->viewer);

    $this->get(BomAdjustmentPage::getUrl())
        ->assertOk()
        ->assertSee('Research & Development')
        ->assertSee('BOM Adjustment')
        ->assertSee(BomAdjustmentPage::getUrl(), false);
});

it('searches filters and paginates the local BOM catalog with default page size 20', function () {
    RndBomCatalog::factory()->create(['bom_code' => 'BOM-CRS', 'bom_name' => 'Croissant Assembly', 'product_code' => 'CRS', 'uom_name' => 'PCS', 'is_active' => true]);
    RndBomCatalog::factory()->create(['bom_code' => 'BOM-DNT', 'bom_name' => 'Donut Assembly', 'product_code' => 'DNT', 'uom_name' => 'GRAM', 'is_active' => false]);
    RndBomCatalog::factory()->count(23)->create(['uom_name' => 'PCS', 'is_active' => true]);

    $this->actingAs($this->viewer);

    $page = Livewire::test(BomAdjustmentPage::class)
        ->assertSee('Croissant Assembly')
        ->assertSee('Donut Assembly');

    expect($page->instance()->catalogRows()->perPage())->toBe(20)
        ->and($page->instance()->catalogRows()->total())->toBe(25);

    $page->set('search', 'croissant')->assertSee('Croissant Assembly')->assertDontSee('Donut Assembly');
    $page->set('search', '')->set('statusFilter', 'inactive')->assertSee('Donut Assembly')->assertDontSee('Croissant Assembly');
    $page->set('statusFilter', '')->set('perPage', 10);
    expect($page->instance()->catalogRows()->perPage())->toBe(10);
});

it('jumps to a numbered page in the catalog and clamps out-of-range values', function () {
    RndBomCatalog::factory()->count(45)->create();
    $this->actingAs($this->viewer);

    $page = Livewire::test(BomAdjustmentPage::class)->set('perPage', 10);
    expect($page->instance()->catalogRows()->lastPage())->toBe(5);

    $page->call('goToPage', 3)->assertSeeHtml('aria-current="page"');
    expect($page->get('page'))->toBe(3);

    $page->call('goToPage', 999);
    expect($page->get('page'))->toBe(5);

    $page->call('goToPage', 0);
    expect($page->get('page'))->toBe(1);
});

it('hides the edit link in the catalog table for users without edit permission', function () {
    RndBomCatalog::factory()->create(['bom_code' => 'BOM-CRS', 'esb_bom_id' => 77]);
    $this->actingAs($this->viewer);

    Livewire::test(BomAdjustmentPage::class)->assertDontSee('bom-adjustments/77/edit', false);

    $this->actingAs($this->editor);
    Livewire::test(BomAdjustmentPage::class)->assertSee('bom-adjustments/77/edit', false);
});

it('hides the Change History tab from users without the history permission', function () {
    $this->actingAs($this->viewer);
    Livewire::test(BomAdjustmentPage::class)->assertDontSee('Change History');

    $this->actingAs($this->historyViewer);
    Livewire::test(BomAdjustmentPage::class)->assertSee('Change History');
});

it('searches filters and paginates Change History and shows a detail modal', function () {
    $actor = User::factory()->create(['name' => 'Kasih Budi', 'username' => 'kasih.budi']);
    RndBomChangeLog::factory()->create([
        'bom_name' => 'Croissant Assembly', 'product_name' => 'Croissant', 'source' => RndBomChangeLogSource::BomAdjustment,
        'status' => RndBomChangeLogStatus::Success, 'changed_by' => $actor->id, 'reason' => 'Naikkan porsi',
        'changes' => ['has_changes' => true, 'components_added' => [], 'components_removed' => [], 'components_changed' => []],
    ]);
    RndBomChangeLog::factory()->create(['bom_name' => 'Donut Assembly', 'product_name' => 'Donut', 'source' => RndBomChangeLogSource::ExternalEsb, 'event' => 'external_change_detected', 'status' => RndBomChangeLogStatus::Success, 'changed_by' => null]);

    $this->actingAs($this->historyViewer);

    $page = Livewire::test(BomAdjustmentPage::class)
        ->set('tab', 'history')
        ->assertSee('Croissant Assembly')
        ->assertSee('Donut Assembly')
        ->assertSee('kasih.budi')
        ->assertSee('Tidak diketahui');

    $page->set('historySearch', 'Croissant')->assertSee('Croissant Assembly')->assertDontSee('Donut Assembly');
    $page->set('historySearch', '')->set('historySourceFilter', RndBomChangeLogSource::ExternalEsb->value)->assertSee('Donut Assembly')->assertDontSee('Croissant Assembly');
    $page->set('historySourceFilter', '')->set('historyEventFilter', 'external_change_detected')->assertSee('Donut Assembly')->assertDontSee('Croissant Assembly');
    $page->set('historyEventFilter', '')->set('historyUserFilter', (string) $actor->id)->assertSee('Croissant Assembly')->assertDontSee('Donut Assembly');

    $log = RndBomChangeLog::query()->where('bom_name', 'Croissant Assembly')->sole();
    $page->set('historySourceFilter', '')
        ->call('showHistoryDetail', $log->id)
        ->assertSee('Naikkan porsi');
});

it('shows a component change table in the detail modal for a confirmed change', function () {
    $log = RndBomChangeLog::factory()->create([
        'bom_name' => 'Croissant Assembly',
        'status' => RndBomChangeLogStatus::Success,
        'changes' => [
            'has_changes' => true,
            'components_added' => [['productCode' => 'SGR', 'productName' => 'Sugar', 'uomName' => 'GR', 'qty' => 20]],
            'components_removed' => [],
            'components_changed' => [['productCode' => 'BTR', 'productName' => 'Butter', 'uomName' => 'GR', 'before_qty' => 100.0, 'after_qty' => 150.0]],
        ],
    ]);

    $this->actingAs($this->historyViewer);

    Livewire::test(BomAdjustmentPage::class)
        ->set('tab', 'history')
        ->call('showHistoryDetail', $log->id)
        ->assertSee('Verified by ESB')
        ->assertSee('Sugar')
        ->assertSee('Added')
        ->assertSee('Butter')
        ->assertSee('Qty Changed')
        ->assertSee('100,00')
        ->assertSee('150,00');
});

it('computes a live diff in the detail modal when no confirmed changes exist yet', function () {
    $log = RndBomChangeLog::factory()->create([
        'bom_name' => 'Tiramisu Assembly',
        'status' => RndBomChangeLogStatus::Pending,
        'changes' => null,
        'before_snapshot' => ['productDetailID' => 100, 'bomDetails' => [
            ['productDetailID' => 200, 'productCode' => 'MSC', 'productName' => 'Mascarpone', 'uomName' => 'GR', 'qty' => 120],
        ]],
        'requested_snapshot' => ['productDetailID' => 100, 'bomDetails' => [
            ['productDetailID' => 200, 'productCode' => 'MSC', 'productName' => 'Mascarpone', 'uomName' => 'GR', 'qty' => 140],
        ]],
    ]);

    $this->actingAs($this->historyViewer);

    Livewire::test(BomAdjustmentPage::class)
        ->set('tab', 'history')
        ->call('showHistoryDetail', $log->id)
        ->assertSee('Requested (not yet verified)')
        ->assertSee('Mascarpone')
        ->assertSee('120,00')
        ->assertSee('140,00');
});

it('only shows the reconcile action for permitted users on Needs Reconciliation records', function () {
    RndBomChangeLog::factory()->create(['status' => RndBomChangeLogStatus::NeedsReconciliation, 'bom_code' => 'BOM-NR']);
    RndBomChangeLog::factory()->create(['status' => RndBomChangeLogStatus::Success, 'bom_code' => 'BOM-OK']);

    $this->actingAs($this->historyViewer);
    Livewire::test(BomAdjustmentPage::class)->set('tab', 'history')->assertDontSee('Reconcile');

    $this->actingAs($this->reconciler);
    Livewire::test(BomAdjustmentPage::class)->set('tab', 'history')->assertSeeHtml('wire:click="reconcile(');
});

it('blocks a non-permitted user from calling reconcile directly', function () {
    $log = RndBomChangeLog::factory()->create(['status' => RndBomChangeLogStatus::NeedsReconciliation]);
    $this->actingAs($this->historyViewer);

    Livewire::test(BomAdjustmentPage::class)->set('tab', 'history')
        ->call('reconcile', $log->id)
        ->assertForbidden();
});
