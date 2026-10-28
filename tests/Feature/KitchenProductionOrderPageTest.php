<?php

use App\Enums\EsbProductionOrderSyncMode;
use App\Enums\EsbProductionOrderSyncStatus;
use App\Filament\Helpdesk\Pages\KitchenProductionSchedulePage;
use App\Jobs\Kitchen\SyncEsbProductionOrdersJob;
use App\Models\Branch;
use App\Models\BranchEsbCode;
use App\Models\EsbProductionOrder;
use App\Models\EsbProductionOrderDetail;
use App\Models\EsbProductionOrderSyncRun;
use App\Models\User;
use App\Services\Kitchen\ProductionOrderCalendarQuery;
use App\Services\Kitchen\ProductionOrderDetailBuilder;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function productionOrderPageUser(Branch $branch, array $permissions = ['view kitchen production schedules']): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo($permissions);
    $user->syncBranchAccess([$branch->id], $branch->id);

    return $user->refresh();
}

function productionOrderPageMapping(Branch $branch, array $overrides = []): BranchEsbCode
{
    return $branch->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'KTJ', 'esb_branch_id' => 500 + $branch->id, 'is_active' => true, ...$overrides]);
}

/** @return list<int> */
function calendarOrderIds($page): array
{
    return collect($page->instance()->calendar()['weeks'])->flatten(1)->pluck('orders')->flatten()->pluck('id')->all();
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    Http::preventStrayRequests();
    $this->travelTo('2026-10-14 10:00:00');
    config()->set('kitchen.production_orders.global_credential_companies', []);
    config()->set('esb.core.companies.BLSS', ['username' => 'user', 'password' => 'secret']);
    $this->branch = Branch::factory()->kitchen()->create(['name' => 'Semarang', 'is_active' => true]);
    $this->mapping = productionOrderPageMapping($this->branch);
    $this->user = productionOrderPageUser($this->branch);
    $this->actingAs($this->user);
});

// ── Page access and layout ─────────────────────────────────────────────────

it('opens for authorized users and keeps the Kitchen sidebar group expanded', function () {
    $this->user->givePermissionTo('access backoffice');

    $this->get(KitchenProductionSchedulePage::getUrl())
        ->assertOk()
        ->assertSee('Kalender Production Order')
        ->assertSee('openGroups: ["kitchen"]', false);
});

it('forbids users without view permission and rechecks access on Livewire requests', function () {
    $page = Livewire::test(KitchenProductionSchedulePage::class);
    $this->user->revokePermissionTo('view kitchen production schedules');

    $page->call('nextMonth')->assertForbidden();
});

it('uses the complete visible Monday-to-Sunday range', function () {
    [$start, $end] = app(ProductionOrderCalendarQuery::class)->visibleRange(CarbonImmutable::parse('2026-10-01'));

    expect([$start->toDateString(), $end->toDateString()])->toBe(['2026-09-28', '2026-11-01']);
});

it('offers no way to create or change data and needs no environment toggle (prd.md §38)', function () {
    EsbProductionOrder::factory()->forMapping($this->mapping)->create(['production_order_date' => '2026-10-08']);

    Livewire::test(KitchenProductionSchedulePage::class)
        ->assertSee('Kalender Production Order')
        ->assertDontSee('Buat Jadwal')
        ->assertDontSee('Tambah BOM Assembly')
        ->assertDontSee('Sumber')
        ->assertDontSeeHtml('wire:click="openCreate')
        ->assertDontSeeHtml('wire:submit');

    expect(config('kitchen.production_orders'))->not->toHaveKey('enabled');
});

it('removes the local plan tables and mutation permissions', function () {
    expect(Schema::hasTable('kitchen_production_schedules'))->toBeFalse()
        ->and(Schema::hasTable('kitchen_production_schedule_items'))->toBeFalse()
        ->and(Permission::query()->whereIn('name', ['create kitchen production schedules', 'update kitchen production schedules', 'cancel kitchen production schedules'])->exists())->toBeFalse()
        ->and(collect(config('permissions'))->flatten()->all())->not->toContain('create kitchen production schedules');
});

// ── Calendar ───────────────────────────────────────────────────────────────

it('shows ESB orders on their production date with an ESB badge in desktop and mobile data', function () {
    $order = EsbProductionOrder::factory()->forMapping($this->mapping)->create(['production_order_num' => 'PO-7788', 'production_order_date' => '2026-10-09', 'status_name' => 'Authorized']);
    EsbProductionOrderDetail::factory()->count(2)->for($order, 'order')->sequence(['sequence' => 1], ['sequence' => 2])->create();

    $page = Livewire::test(KitchenProductionSchedulePage::class)
        ->assertSee('PO-7788')
        ->assertSee('Authorized · 2 item', false)
        ->assertSeeHtml('kitchen-po-chip-'.$order->id);
    $calendar = $page->instance()->calendar();
    $day = collect($calendar['weeks'])->flatten(1)->first(fn (array $day): bool => $day['date']->toDateString() === '2026-10-09');

    expect(collect($day['orders'])->pluck('id')->all())->toBe([$order->id])
        ->and(collect($calendar['agenda'])->pluck('orders')->flatten()->pluck('id')->all())->toBe([$order->id])
        ->and($calendar['total'])->toBe(1);
});

it('filters by Branch and ESB status on the server', function () {
    $authorized = EsbProductionOrder::factory()->forMapping($this->mapping)->create(['production_order_date' => '2026-10-08', 'status_id' => '2', 'status_name' => 'Authorized']);
    $finished = EsbProductionOrder::factory()->forMapping($this->mapping)->create(['production_order_date' => '2026-10-09', 'status_id' => '5', 'status_name' => 'Finished']);
    $page = Livewire::test(KitchenProductionSchedulePage::class);

    expect($page->instance()->statusOptions())->toBe(['2' => 'Authorized', '5' => 'Finished'])
        ->and(calendarOrderIds($page))->toBe([$authorized->id, $finished->id]);

    $page->set('filterStatus', '5');
    expect(calendarOrderIds($page))->toBe([$finished->id]);

    $page->set('filterStatus', '')->set('filterBranchId', (string) Branch::factory()->kitchen()->create()->id);
    expect(calendarOrderIds($page))->toBe([]);
    $page->assertSee('Tidak ada Production Order ESB pada periode dan filter ini.');

    $page->call('resetFilters')->assertSet('filterBranchId', '')->assertSet('filterStatus', '');
});

it('says when the month has no Production Order', function () {
    Livewire::test(KitchenProductionSchedulePage::class)->assertSee('Belum ada Production Order ESB pada bulan ini.');
});

it('keeps the calendar query count flat and never loads materials', function () {
    $page = Livewire::test(KitchenProductionSchedulePage::class);
    $count = function () use ($page): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        unset($page->instance()->calendar);
        $page->instance()->calendar();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return [count($log), collect($log)->pluck('query')->implode("\n")];
    };

    EsbProductionOrder::factory()->forMapping($this->mapping)->has(EsbProductionOrderDetail::factory(), 'details')->create(['production_order_date' => '2026-10-02']);
    [$few] = $count();

    foreach (range(3, 20) as $day) {
        EsbProductionOrder::factory()->forMapping($this->mapping)->has(EsbProductionOrderDetail::factory(), 'details')->create(['production_order_date' => sprintf('2026-10-%02d', $day)]);
    }

    [$many, $sql] = $count();

    expect($many)->toBeLessThanOrEqual($few + 1)->and($sql)->not->toContain('materials');
});

it('never calls ESB while rendering the calendar or opening a detail', function () {
    $order = EsbProductionOrder::factory()->forMapping($this->mapping)->create();

    Livewire::test(KitchenProductionSchedulePage::class)->call('openProductionOrder', $order->id)->assertSet('productionOrderId', $order->id);

    Http::assertNothingSent();
});

// ── Authorization and Kitchen scope ────────────────────────────────────────

it('limits snapshots to accessible Branches and refuses forged detail IDs', function () {
    $otherBranch = Branch::factory()->kitchen()->create(['is_active' => true]);
    $own = EsbProductionOrder::factory()->forMapping($this->mapping)->create(['production_order_date' => '2026-10-08']);
    $foreign = EsbProductionOrder::factory()->forMapping(productionOrderPageMapping($otherBranch))->create(['production_order_date' => '2026-10-08']);

    $page = Livewire::test(KitchenProductionSchedulePage::class);
    expect(calendarOrderIds($page))->toBe([$own->id]);

    $page->call('openProductionOrder', $foreign->id)->assertSet('productionOrderId', null);
    $page->set('productionOrderId', $foreign->id);
    expect($page->instance()->productionOrderDetail())->toBeNull();
    $page->assertSee('Production Order tidak ditemukan atau Anda tidak lagi memiliki akses.')->assertDontSee($foreign->production_order_num);

    expect(fn () => app(ProductionOrderDetailBuilder::class)->build($this->user, $foreign->id))->toThrow(AuthorizationException::class);
});

it('requires the feature permission even for all-Branch users', function () {
    $order = EsbProductionOrder::factory()->forMapping($this->mapping)->create();
    $allBranch = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $allBranch->givePermissionTo('view all branch kitchen production schedules');

    expect($allBranch->can('view', $order))->toBeFalse()
        ->and(fn () => app(ProductionOrderDetailBuilder::class)->build($allBranch, $order->id))->toThrow(AuthorizationException::class);

    $allBranch->givePermissionTo('view kitchen production schedules');
    expect($allBranch->refresh()->can('view', $order))->toBeTrue();
});

it('lets the view-all-Branch permission widen the scope to every Kitchen Branch', function () {
    $otherBranch = Branch::factory()->kitchen()->create(['name' => 'Kitchen Jakarta', 'is_active' => true]);
    $foreign = EsbProductionOrder::factory()->forMapping(productionOrderPageMapping($otherBranch))->create(['production_order_date' => '2026-10-08']);
    $this->user->givePermissionTo('view all branch kitchen production schedules');

    $page = Livewire::test(KitchenProductionSchedulePage::class)->assertSee('Semua Kitchen');

    expect(calendarOrderIds($page))->toContain($foreign->id)
        ->and($page->instance()->filterBranches()->pluck('id')->all())->toBe([$otherBranch->id, $this->branch->id]);
});

it('keeps non-Kitchen Branches out of filters, calendar, detail, and sync status for all-Branch users and SUPERADMIN', function (string $access) {
    $store = Branch::factory()->create(['name' => 'Patisserie Pusat', 'is_active' => true]);
    $order = EsbProductionOrder::factory()->forMapping(productionOrderPageMapping($store))->create(['production_order_date' => '2026-10-08']);
    match ($access) {
        'all-branch' => $this->user->forceFill(['access_all_branches' => true])->save(),
        'view-all' => $this->user->givePermissionTo('view all branch kitchen production schedules'),
        'superadmin' => $this->user->assignRole('SUPERADMIN'),
    };
    $user = $this->user->refresh();

    $page = Livewire::test(KitchenProductionSchedulePage::class)->set('filterBranchId', (string) $store->id);

    expect(calendarOrderIds($page))->toBe([])
        ->and($page->instance()->filterBranches()->pluck('id')->all())->not->toContain($store->id)
        ->and(app(ProductionOrderCalendarQuery::class)->statusOptions($user))->toBe([])
        ->and(collect(app(ProductionOrderCalendarQuery::class)->syncSummary($user)['groups'])->flatten()->all())->not->toContain('Patisserie Pusat')
        ->and(app(ProductionOrderCalendarQuery::class)->syncSummary($user, $store->id)['groups'])->toBe([]);
    $page->assertDontSee('Patisserie Pusat')->call('openProductionOrder', $order->id)->assertSet('productionOrderId', null);
    expect(fn () => app(ProductionOrderDetailBuilder::class)->build($user, $order->id))->toThrow(ModelNotFoundException::class);
})->with(['all-branch', 'view-all', 'superadmin']);

it('keeps non-Kitchen snapshots out of reach for Branch-limited users', function () {
    $store = Branch::factory()->create(['is_active' => true]);
    $order = EsbProductionOrder::factory()->forMapping(productionOrderPageMapping($store))->create(['production_order_date' => '2026-10-08']);
    $this->user->syncBranchAccess([$this->branch->id, $store->id], $this->branch->id);

    expect(calendarOrderIds(Livewire::test(KitchenProductionSchedulePage::class)))->not->toContain($order->id)
        ->and($this->user->refresh()->can('view', $order))->toBeFalse();
});

it('says when the user has no Kitchen Branch', function () {
    $store = Branch::factory()->create(['is_active' => true]);
    $this->user->syncBranchAccess([$store->id], $store->id);

    Livewire::test(KitchenProductionSchedulePage::class)->assertSee('Belum ada Branch Kitchen yang dapat Anda akses.');
});

// ── Detail and sync states ─────────────────────────────────────────────────

it('shows a read-only detail with details and materials and no mutation action', function () {
    $order = EsbProductionOrder::factory()->forMapping($this->mapping)->create(['production_order_num' => 'PO-9001', 'additional_info' => 'Produksi pagi', 'authorized_by_name' => 'Supervisor Contoh']);
    EsbProductionOrderDetail::factory()->for($order, 'order')->create(['bom_name' => 'Adonan Roti Contoh', 'quantity' => '12.5000', 'uom_name' => 'KG', 'is_finished' => true]);

    Livewire::test(KitchenProductionSchedulePage::class)
        ->call('openProductionOrder', $order->id)
        ->assertSee('Production Order ESB · PO-9001')
        ->assertSee('Hanya baca — perubahan dilakukan di ESB')
        ->assertSee('Adonan Roti Contoh')
        ->assertSee('12,5')
        ->assertSee('Selesai')
        ->assertSee('Material (1)')
        ->assertSee('Tepung Contoh')
        ->assertSee('Produksi pagi')
        ->assertSee('Supervisor Contoh')
        ->assertDontSeeHtml('wire:submit')
        ->call('closeModal')
        ->assertSet('productionOrderId', null);
});

it('tells the user when details are still being read and when ESB no longer returned the order', function () {
    $pending = EsbProductionOrder::factory()->forMapping($this->mapping)->withoutDetails()->create(['source_last_seen_at' => now()->subHours(2)]);
    EsbProductionOrderSyncRun::factory()->forMapping($this->mapping)->create(['date_from' => '2026-10-07', 'date_to' => '2026-10-28', 'started_at' => now()->subHour(), 'list_completed_at' => now()->subHour()]);

    Livewire::test(KitchenProductionSchedulePage::class)
        ->assertSee('detail diproses')
        ->call('openProductionOrder', $pending->id)
        ->assertSee('Detail Production Order ini sedang diambil dari ESB.')
        ->assertSee('Tidak terlihat pada sinkronisasi terakhir.');
});

it('shows a state for each sync situation', function (Closure $arrange, string $message) {
    $arrange($this);

    Livewire::test(KitchenProductionSchedulePage::class)->assertSee($message);
})->with([
    'no mapping' => [fn ($test) => $test->mapping->delete(), 'Cabang ini belum terhubung ke ESB.'],
    'incomplete mapping' => [fn ($test) => $test->mapping->update(['esb_branch_id' => null]), 'Mapping ESB cabang ini belum lengkap'],
    'company not registered' => [fn ($test) => $test->mapping->update(['esb_comcode' => 'ZZNEW']), 'Mapping ESB cabang ini belum lengkap'],
    'never synced' => [fn () => null, 'Production Order ESB belum pernah disinkronkan untuk cabang ini.'],
    'running' => [fn ($test) => EsbProductionOrderSyncRun::factory()->forMapping($test->mapping)->running()->create(), 'Sinkronisasi sedang berjalan.'],
    'stale' => [fn ($test) => EsbProductionOrderSyncRun::factory()->forMapping($test->mapping)->create(['list_completed_at' => now()->subHours(4), 'finished_at' => now()->subHours(4)]), 'Data Production Order mungkin belum terbaru.'],
    'partial' => [fn ($test) => EsbProductionOrderSyncRun::factory()->forMapping($test->mapping)->partial()->create(), 'Sebagian detail Production Order belum berhasil diambil.'],
    'failed with snapshot' => [function ($test) {
        EsbProductionOrder::factory()->forMapping($test->mapping)->create(['production_order_date' => '2026-10-08']);
        EsbProductionOrderSyncRun::factory()->forMapping($test->mapping)->create(['list_completed_at' => now()->subDay()]);
        EsbProductionOrderSyncRun::factory()->forMapping($test->mapping)->failed()->create([
            'error_code' => '403',
            'error_summary' => 'Credential ESB tidak mempunyai akses Production Order [BLSS].',
        ]);
    }, 'Sinkronisasi terakhir gagal. Menampilkan data terakhir yang tersimpan.'],
]);

it('shows a sanitized failure reason without exposing credentials', function () {
    EsbProductionOrderSyncRun::factory()->forMapping($this->mapping)->failed()->create([
        'error_code' => '403',
        'error_summary' => 'Credential ditolak token=super-secret-token https://services.esb.co.id/core/production/production-order?access_token=unsafe',
        'finished_at' => now()->setTime(14, 4),
    ]);

    Livewire::test(KitchenProductionSchedulePage::class)
        ->assertSee('Credential ditolak token=[REDACTED] https://services.esb.co.id/core/production/production-order')
        ->assertSee('(kode 403)')
        ->assertSee(now()->setTime(14, 4)->locale('id')->translatedFormat('d M Y H:i'))
        ->assertDontSee('super-secret-token')
        ->assertDontSee('access_token=unsafe');
});

it('reports the last successful sync time and no warning when fresh', function () {
    EsbProductionOrderSyncRun::factory()->forMapping($this->mapping)->create(['list_completed_at' => now()->subMinutes(20)]);

    $summary = app(ProductionOrderCalendarQuery::class)->syncSummary($this->user);

    expect($summary['groups'])->toBe(['ok' => ['Semarang']])
        ->and($summary['lastSyncedAt']->toDateTimeString())->toBe(now()->subMinutes(20)->toDateTimeString());
    Livewire::test(KitchenProductionSchedulePage::class)
        ->assertSee('Terakhir disinkronkan')
        ->assertDontSee('mungkin belum terbaru');
});

it('treats a Kitchen mapping of any registered company as synced data without extra configuration (K20)', function () {
    config()->set('esb.core.companies.BLO13', ['username' => 'user-13', 'password' => 'secret-13']);
    $surabaya = Branch::factory()->kitchen()->create(['name' => 'Kitchen Surabaya', 'is_active' => true]);
    $mapping = productionOrderPageMapping($surabaya, ['esb_comcode' => 'BLO13', 'esb_branch_code' => 'HOF']);
    EsbProductionOrderSyncRun::factory()->forMapping($mapping)->create(['list_completed_at' => now()->subMinutes(10)]);
    $this->user->givePermissionTo('view all branch kitchen production schedules');

    expect(app(ProductionOrderCalendarQuery::class)->syncSummary($this->user->refresh(), $surabaya->id)['groups'])->toBe(['ok' => ['Kitchen Surabaya']]);
});

// ── Manual sync (prd.md §39) ───────────────────────────────────────────────

it('shows the sync button only with the sync permission and refuses a direct call without it', function () {
    Queue::fake();

    Livewire::test(KitchenProductionSchedulePage::class)
        ->assertDontSee('Sync Data')
        ->call('syncNow')
        ->assertForbidden();

    Queue::assertNothingPushed();

    $this->user->givePermissionTo('sync kitchen production orders');
    Livewire::test(KitchenProductionSchedulePage::class)->assertSee('Sync Data')->assertSeeHtml('data-sync-icon><svg');
});

it('queues the incremental window for every accessible Kitchen mapping and shows it running', function () {
    Queue::fake();
    $this->user->givePermissionTo(['sync kitchen production orders', 'view all branch kitchen production schedules']);
    $jakarta = Branch::factory()->kitchen()->create(['name' => 'Kitchen Jakarta', 'is_active' => true]);
    $jakartaMapping = productionOrderPageMapping($jakarta, ['esb_comcode' => 'BLSS', 'esb_branch_code' => 'TCHL']);
    $store = Branch::factory()->create(['is_active' => true]);
    productionOrderPageMapping($store);

    $page = Livewire::test(KitchenProductionSchedulePage::class)->call('syncNow')->assertNotified('Sinkronisasi 2 cabang diantrekan.');

    Queue::assertPushed(SyncEsbProductionOrdersJob::class, 2);
    expect(EsbProductionOrderSyncRun::query()->pluck('branch_esb_code_id')->sort()->values()->all())->toBe([$this->mapping->id, $jakartaMapping->id])
        ->and(EsbProductionOrderSyncRun::query()->first()->only(['mode']))->toBe(['mode' => EsbProductionOrderSyncMode::Incremental])
        ->and(EsbProductionOrderSyncRun::query()->first()->date_from->toDateString())->toBe('2026-10-07');

    $page->assertSee('Sinkronisasi sedang berjalan.')->assertSee('Sync Data')->assertSeeHtml('wire:poll.5s');
});

it('limits the sync to the filtered Branch and refuses a Branch outside the Kitchen scope', function () {
    Queue::fake();
    $this->user->givePermissionTo('sync kitchen production orders');
    $other = Branch::factory()->kitchen()->create(['is_active' => true]);
    productionOrderPageMapping($other);

    Livewire::test(KitchenProductionSchedulePage::class)
        ->set('filterBranchId', (string) $this->branch->id)
        ->call('syncNow')
        ->assertNotified('Sinkronisasi 1 cabang diantrekan.');
    expect(EsbProductionOrderSyncRun::query()->pluck('branch_esb_code_id')->all())->toBe([$this->mapping->id]);

    Livewire::test(KitchenProductionSchedulePage::class)
        ->set('filterBranchId', (string) $other->id)
        ->call('syncNow')
        ->assertForbidden();
    Queue::assertPushed(SyncEsbProductionOrdersJob::class, 1);
});

it('never syncs a non-Kitchen Branch, even for SUPERADMIN', function () {
    Queue::fake();
    $this->user->assignRole('SUPERADMIN');
    $store = Branch::factory()->create(['is_active' => true]);
    productionOrderPageMapping($store);

    Livewire::test(KitchenProductionSchedulePage::class)->set('filterBranchId', (string) $store->id)->call('syncNow')->assertForbidden();
    Livewire::test(KitchenProductionSchedulePage::class)->call('syncNow');

    expect(EsbProductionOrderSyncRun::query()->pluck('branch_esb_code_id')->all())->toBe([$this->mapping->id]);
});

it('ignores repeated clicks while a sync runs and during the cooldown', function () {
    Queue::fake();
    $this->user->givePermissionTo('sync kitchen production orders');

    Livewire::test(KitchenProductionSchedulePage::class)->call('syncNow')->assertNotified('Sinkronisasi 1 cabang diantrekan.');
    Livewire::test(KitchenProductionSchedulePage::class)->call('syncNow')->assertNotified('Data baru saja disinkronkan.');

    $run = EsbProductionOrderSyncRun::query()->sole();
    $run->update(['status' => EsbProductionOrderSyncStatus::Succeeded, 'active_key' => null, 'list_completed_at' => now(), 'finished_at' => now()]);
    $this->travel(3)->minutes();
    Livewire::test(KitchenProductionSchedulePage::class)->call('syncNow')->assertNotified('Data baru saja disinkronkan.');

    $this->travel(3)->minutes();
    Livewire::test(KitchenProductionSchedulePage::class)->call('syncNow')->assertNotified('Sinkronisasi 1 cabang diantrekan.');

    Queue::assertPushed(SyncEsbProductionOrdersJob::class, 2);
});

it('explains when no mapping can be synced', function () {
    Queue::fake();
    $this->user->givePermissionTo('sync kitchen production orders');
    $this->mapping->update(['esb_branch_id' => null]);

    Livewire::test(KitchenProductionSchedulePage::class)->call('syncNow')->assertNotified('Tidak ada cabang yang dapat disinkronkan.');
    Queue::assertNothingPushed();
});
