<?php

use App\Actions\Rnd\InternalMemo\UpdateInternalMemoBranchesAction;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ListRndInternalMemos;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ViewRndInternalMemo;
use App\Jobs\SyncInternalMemoMenuCatalogJob;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoBranch;
use App\Models\RndInternalMemoCatalogSync;
use App\Models\RndInternalMemoMenu;
use App\Models\RndInternalMemoMenuCatalog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * docs/rnd-internal-memo-brand-prd.md §11, §12, §18, §23.7: the Memo Internal pages ask for one
 * Brand, show the Brand snapshot, and have no Branch Tujuan, company, or Branch filter left.
 * Replaces the multi-branch form tests whose requirements the Brand PRD supersedes.
 */
beforeEach(function () {
    Queue::fake();
    markInternalMemoCatalogSynced('BLS');
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->bakery = Brand::factory()->create(['name' => 'Bloomery Bakery']);
    $this->coffee = Brand::factory()->create(['name' => 'Arunika Coffee']);

    $this->operator = User::factory()->create(['is_active' => true]);
    $this->operator->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo', 'create rnd internal memo', 'update rnd internal memo']);
    $this->actingAs($this->operator);
});

function brandMemoForm(Testable $page, int $brandId, string $number = 'BR-001', string $period = '2026-09'): Testable
{
    return $page->call('openCreateModal')
        ->set('memoNumber', $number)
        ->set('memoTitle', 'Rilis Menu')
        ->set('periodMonth', $period)
        ->set('brandId', $brandId);
}

it('shows one Brand select, alphabetically, and no Branch Tujuan or company on the create form', function () {
    Livewire::test(ListRndInternalMemos::class)
        ->call('openCreateModal')
        ->assertSee('Brand *')
        ->assertSeeInOrder(['Arunika Coffee', 'Bloomery Bakery'])
        ->assertDontSee('Branch Tujuan')
        ->assertDontSee('Company Code dan Branch Code');
});

it('requires a valid Brand and keeps the modal open with the error on the Brand field', function () {
    brandMemoForm(Livewire::test(ListRndInternalMemos::class), 0)
        ->set('brandId', null)
        ->call('createMemo')
        ->assertHasErrors(['brandId' => 'required'])
        ->assertSet('createModalOpen', true)
        ->assertSet('memoTitle', 'Rilis Menu');

    brandMemoForm(Livewire::test(ListRndInternalMemos::class), 999999)
        ->call('createMemo')
        ->assertHasErrors(['brandId']);

    expect(RndInternalMemo::query()->count())->toBe(0);
});

it('lets different Brands share a period but refuses a second active Memo of the same Brand', function () {
    brandMemoForm(Livewire::test(ListRndInternalMemos::class), $this->bakery->id, 'BR-001')->call('createMemo')->assertHasNoErrors();
    brandMemoForm(Livewire::test(ListRndInternalMemos::class), $this->coffee->id, 'BR-002')->call('createMemo')->assertHasNoErrors();

    brandMemoForm(Livewire::test(ListRndInternalMemos::class), $this->bakery->id, 'BR-003')
        ->call('createMemo')
        ->assertHasErrors(['periodMonth'])
        ->assertSee('Memo Brand Bloomery Bakery untuk periode dan revisi ini sudah ada.');

    expect(RndInternalMemo::query()->pluck('brand_id')->sort()->values()->all())->toBe(collect([$this->bakery->id, $this->coffee->id])->sort()->values()->all());
});

it('ignores any company or branch values the client tries to send on create', function () {
    $page = brandMemoForm(Livewire::test(ListRndInternalMemos::class), $this->coffee->id);

    expect(fn () => $page->set('companyCode', 'BLO6'))->toThrow(Exception::class)
        ->and(fn () => $page->set('branchIds', [1]))->toThrow(Exception::class);

    $page->call('createMemo')->assertHasNoErrors();
    expect(RndInternalMemo::sole()->company_code)->toBe('BLSS');
});

it('shows the Brand snapshot on the index, searches it, filters by Brand, and badges unresolved legacy Memos', function () {
    RndInternalMemo::factory()->forBrand($this->bakery)->create(['title' => 'Memo Roti', 'period_month' => '2026-01-01']);
    RndInternalMemo::factory()->forBrand($this->coffee)->create(['title' => 'Memo Kopi', 'period_month' => '2026-01-01']);
    RndInternalMemo::factory()->withoutBrand()->create(['title' => 'Memo Lama', 'period_month' => '2025-01-01']);

    Livewire::test(ListRndInternalMemos::class)
        ->assertSee('Bloomery Bakery')
        ->assertSee('Arunika Coffee')
        ->assertSee('Brand belum ditentukan')
        ->assertDontSee('Branch Tujuan')
        ->set('search', 'Arunika')
        ->assertSee('Memo Kopi')
        ->assertDontSee('Memo Roti')
        ->set('search', '')
        ->set('brandFilter', (string) $this->bakery->id)
        ->assertSee('Memo Roti')
        ->assertDontSee('Memo Kopi')
        ->set('brandFilter', 'unresolved')
        ->assertSee('Memo Lama')
        ->assertDontSee('Memo Roti');
});

it('keeps the snapshot after the Master Brand is renamed or deleted', function () {
    $memo = RndInternalMemo::factory()->forBrand($this->bakery)->create();

    $this->bakery->update(['name' => 'Nama Baru']);
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->assertSeeInOrder(['Brand', 'Bloomery Bakery'])->assertDontSee('Nama Baru');

    $this->bakery->delete();
    $memo->refresh();
    expect($memo->brand_id)->toBeNull()->and($memo->brand_name_snapshot)->toBe('Bloomery Bakery');
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->assertSeeInOrder(['Brand', 'Bloomery Bakery']);
});

it('shows the Brand and the BLSS data source on the detail page without any Branch section', function () {
    $memo = RndInternalMemo::factory()->forBrand($this->bakery)->create();

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->assertSeeInOrder(['Brand', 'Bloomery Bakery'])
        ->assertDontSee('Branch Tujuan')
        ->assertDontSee('Perlu Menentukan Branch');

    $legacy = RndInternalMemo::factory()->withoutBrand()->create();
    Livewire::test(ViewRndInternalMemo::class, ['record' => $legacy->id])
        ->assertSee('Brand belum ditentukan')
        ->assertSee('Memo lama ini belum mempunyai Brand');
});

it('edits the Brand as metadata only: Menus, items, Minimum Orders, legacy rows untouched and no sync queued', function () {
    $memo = RndInternalMemo::factory()->forBrand($this->bakery)->create(['period_month' => '2026-09-01']);
    $menu = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id, 'forecast_quantity' => 7, 'bom_snapshot' => ['bomID' => 42]]);
    $material = $menu->materials()->create([
        'product_code' => 'RAW', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 1, 'net_quantity' => 7,
        'minimum_order' => 25, 'source_bom_id' => 42, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false,
    ]);
    Http::fake();

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openEditMemoModal')
        ->assertSet('brandId', $this->bakery->id)
        ->set('brandId', $this->coffee->id)
        ->call('saveMemoInfo')
        ->assertHasNoErrors()
        ->assertSet('editMemoModalOpen', false)
        ->assertNotified('Brand Memo berhasil diperbarui');

    $memo->refresh();
    expect($memo->brand_id)->toBe($this->coffee->id)
        ->and($memo->brand_name_snapshot)->toBe('Arunika Coffee')
        ->and($memo->company_code)->toBe('BLSS')
        ->and($menu->fresh()->only(['forecast_quantity', 'bom_snapshot']))->toBe(['forecast_quantity' => '7.00', 'bom_snapshot' => ['bomID' => 42]])
        ->and((float) $material->fresh()->minimum_order)->toBe(25.0)
        ->and($memo->branches()->count())->toBe(0);
    Queue::assertNotPushed(SyncInternalMemoMenuCatalogJob::class);
    Http::assertNothingSent();
});

it('keeps the edit modal and its input when the new Brand already has a Memo for the period', function () {
    RndInternalMemo::factory()->forBrand($this->coffee)->create(['period_month' => '2026-09-01']);
    $memo = RndInternalMemo::factory()->forBrand($this->bakery)->create(['period_month' => '2026-09-01', 'title' => 'Judul Lama']);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openEditMemoModal')
        ->set('memoTitle', 'Judul Baru')
        ->set('brandId', $this->coffee->id)
        ->call('saveMemoInfo')
        ->assertHasErrors(['periodMonth'])
        ->assertSet('editMemoModalOpen', true)
        ->assertSet('memoTitle', 'Judul Baru');

    expect($memo->fresh()->only(['brand_id', 'title']))->toBe(['brand_id' => $this->bakery->id, 'title' => 'Judul Lama']);
});

it('asks an unresolved legacy Memo to pick a Brand before saving', function () {
    $legacy = RndInternalMemo::factory()->withoutBrand()->create();

    Livewire::test(ViewRndInternalMemo::class, ['record' => $legacy->id])
        ->call('openEditMemoModal')
        ->assertSee('Brand belum ditentukan untuk Memo lama ini')
        ->call('saveMemoInfo')
        ->assertHasErrors(['brandId' => 'required'])
        ->set('brandId', $this->bakery->id)
        ->call('saveMemoInfo')
        ->assertHasNoErrors();

    expect($legacy->fresh()->brand_name_snapshot)->toBe('Bloomery Bakery');
});

it('opens the Menu picker with the BLSS source, no Branch/company filter, and marks Menus already chosen', function () {
    $memo = RndInternalMemo::factory()->forBrand($this->bakery)->create();
    RndInternalMemoMenuCatalog::factory()->create(['company_code' => 'BLSS', 'branch_code' => 'BLS', 'menu_id' => 501, 'menu_code' => 'MENU-501', 'menu_name' => 'Croissant', 'bom_id' => 42, 'bom_name' => 'BOM Croissant']);
    RndInternalMemoMenuCatalog::factory()->create(['company_code' => 'BLSS', 'branch_code' => 'BLS', 'menu_id' => 502, 'menu_code' => 'MENU-502', 'menu_name' => 'Donat', 'bom_id' => 43]);
    RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id, 'esb_menu_id' => 501]);
    markInternalMemoCatalogSynced('BLS', now()->subHour()); // stale: a refresh is queued, the snapshot stays usable
    Http::fake();

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openMenuPicker')
        ->call('initializeMenuPicker')
        ->assertSee('Sumber data: ESB BLSS')
        ->assertSee('Sudah Dipilih')
        ->assertSee('BOM Croissant')
        ->assertSee('Pilih Menu Donat', false)
        ->assertDontSee('Semua Branch')
        ->assertDontSee('Semua Company Code')
        ->assertDontSee('Tersedia Di Branch')
        ->assertDontSee('BLS<', false);

    Queue::assertPushed(SyncInternalMemoMenuCatalogJob::class, 1);
    Http::assertNothingSent();
});

it('needs no configuration: a first open queues the sync and shows a syncing state, a failed sync keeps a recoverable state', function () {
    RndInternalMemoCatalogSync::query()->delete();
    $memo = RndInternalMemo::factory()->forBrand($this->bakery)->create();

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openMenuPicker')
        ->call('initializeMenuPicker')
        ->assertSee('Katalog sedang disinkronkan')
        ->assertDontSee('belum dikonfigurasi');
    Queue::assertPushed(SyncInternalMemoMenuCatalogJob::class, 1);

    RndInternalMemoCatalogSync::query()->update(['status' => 'failed', 'last_synced_at' => now()->subDay()]);
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openMenuPicker')
        ->call('loadMenuPage', 1)
        ->assertSee('Pembaruan katalog terakhir gagal')
        ->assertSee('Muat Ulang Katalog');
});

it('authorizes by Memo permission only: no Branch access needed, and missing permission is refused server-side', function () {
    $branch = Branch::factory()->create();
    $memo = RndInternalMemo::factory()->forBrand($this->bakery)->create();
    RndInternalMemoBranch::factory()->create(['rnd_internal_memo_id' => $memo->id, 'branch_id' => $branch->id]);

    $permittedElsewhere = User::factory()->create(['is_active' => true, 'branch_id' => Branch::factory()->create()->id]);
    $permittedElsewhere->givePermissionTo(['view rnd internal memo', 'update rnd internal memo']);
    $viewerOnly = User::factory()->create(['is_active' => true]);
    $viewerOnly->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo']);
    $noPermission = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);

    expect($permittedElsewhere->can('view', $memo))->toBeTrue()
        ->and($permittedElsewhere->can('update', $memo))->toBeTrue()
        ->and($viewerOnly->can('update', $memo))->toBeFalse()
        ->and($noPermission->can('view', $memo))->toBeFalse();

    $this->actingAs($viewerOnly);
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('openEditMemoModal')->assertForbidden();
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('saveMemoInfo')->assertForbidden();
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('addMenu', 501)->assertForbidden();
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('refreshMenuCatalog')->assertForbidden();

    $this->actingAs($noPermission);
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->assertForbidden();
});

/*
 * Legacy compatibility (docs/rnd-internal-memo-brand-prd.md §13.4, §15.2): the deprecated
 * multi-branch action is no longer called by any page but is kept until the cleanup phase, so its
 * own reconciliation rule stays covered.
 */
it('keeps the deprecated Branch update action refusing to orphan an attached Menu', function () {
    $operator = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    config()->set('esb.tokens.BLSS', 'static-blss-token');
    config()->set('esb.tokens.BLO6', 'static-blo6-token');
    $branchA = Branch::factory()->create();
    $branchA->update(['stock_card_esb_code_id' => $branchA->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BLS'])->id]);
    $branchB = Branch::factory()->create();
    $mappingB = $branchB->esbCodes()->create(['esb_comcode' => 'BLO6', 'esb_branch_code' => 'BL6']);
    $branchB->update(['stock_card_esb_code_id' => $mappingB->id]);
    $memo = RndInternalMemo::factory()->withoutBrand()->create();
    $memoBranchB = RndInternalMemoBranch::factory()->create(['rnd_internal_memo_id' => $memo->id, 'branch_id' => $branchB->id, 'branch_esb_code_id' => $mappingB->id, 'company_code_snapshot' => 'BLO6']);
    $menu = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id, 'company_code' => 'BLO6']);
    $menu->branches()->attach($memoBranchB->id);

    expect(fn () => app(UpdateInternalMemoBranchesAction::class)->execute($memo, [$branchA->id], $operator))
        ->toThrow(ValidationException::class);
    expect($memo->branches()->count())->toBe(1);
});

it('shows the Memo form fields in the header and offers Export PDF only to permitted users', function () {
    $memo = RndInternalMemo::factory()->forBrand($this->bakery)->create([
        'memo_number' => '002/RND/X/2026', 'title' => 'Rilis Menu', 'recipient' => 'All Store', 'sender' => 'Tim R&D',
        'subject' => 'Rilis Menu Oktober', 'notes' => 'Siapkan display.', 'period_month' => '2026-10-01', 'memo_date' => '2026-10-05',
    ]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->assertSeeInOrder(['Rilis Menu', 'Bloomery Bakery', 'Daftar Memo', 'Edit Info'])
        ->assertSeeInOrder(['Nomor Memo', '002/RND/X/2026', 'Bulan Memo', 'October 2026', 'Brand', 'Bloomery Bakery', 'Catatan', 'Siapkan display.'])
        ->assertDontSee('Kepada')
        ->assertDontSee('Sumber Data')
        ->assertDontSee('Export PDF');

    $this->operator->givePermissionTo('download rnd internal memo pdf');
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->assertSee('Export PDF')
        ->assertSeeHtml(route('helpdesk.rnd-internal-memos.export-pdf', ['memo' => $memo->id]));
});

it('exports the release memo PDF titled MEMO INTERNAL {BRAND} with the Menu list, for permitted users only', function () {
    $memo = RndInternalMemo::factory()->forBrand($this->bakery)->create(['memo_number' => '002/RND/X/2026', 'title' => 'Rilis Menu Oktober']);
    $memo->menus()->create([
        'company_code' => 'BLSS', 'esb_menu_id' => 501, 'menu_code' => 'MC-WH0001', 'menu_name' => 'Belgian Chocolate Mille Crepe Cake 20cm',
        'category_detail' => 'CAKE - WHOLE CAKE', 'esb_bom_id' => 42, 'release_date' => '2026-10-15', 'menu_snapshot' => [],
    ]);

    $this->get(route('helpdesk.rnd-internal-memos.export-pdf', ['memo' => $memo->id]))->assertForbidden();

    $this->operator->givePermissionTo('download rnd internal memo pdf');
    $response = $this->get(route('helpdesk.rnd-internal-memos.export-pdf', ['memo' => $memo->id]));
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('MEMO-INTERNAL-002-RND-X-2026.pdf');

    $html = view('exports.rnd-internal-memo-release-pdf', [
        'memo' => $memo, 'brandLabel' => $memo->brandLabel(), 'logo' => '', 'generatedAt' => 'now',
        'menus' => collect([['code' => 'MC-WH0001', 'name' => 'Belgian Chocolate Mille Crepe Cake 20cm', 'category' => 'CAKE', 'category_detail' => 'WHOLE CAKE', 'release_date' => now()]]),
    ])->render();
    expect($html)->toContain('Memo Internal Bloomery Bakery')
        ->toContain('PT Bloomery Sekawan Sejahtera')
        ->toContain('Daftar Menu yang Akan Rilis')
        ->toContain('Belgian Chocolate Mille Crepe Cake 20cm')
        ->toContain('WHOLE CAKE')
        ->toContain('text-transform: uppercase');
});

it('lists Memos in one section table aligned with the Memo detail page', function () {
    RndInternalMemo::factory()->forBrand($this->bakery)->create(['title' => 'Rilis Menu Oktober', 'memo_number' => '002/RND/X/2026', 'period_month' => '2026-10-01']);

    Livewire::test(ListRndInternalMemos::class)
        ->assertSeeInOrder(['Memo Internal', '1 Memo', 'Buat Memo', 'Cari', 'Bulan Memo', 'Brand'])
        ->assertSeeInOrder(['Memo', 'Bulan Memo', 'Brand', 'Diperbarui'])
        ->assertSeeInOrder(['Rilis Menu Oktober', '002/RND/X/2026', 'October 2026', 'Bloomery Bakery'])
        ->assertDontSee('0 Menu')
        ->assertSeeHtml('aria-label="Buka Memo Rilis Menu Oktober"');
});
