<?php

use App\Enums\KitchenProductionScheduleStatus;
use App\Filament\Helpdesk\Pages\KitchenProductionSchedulePage;
use App\Models\Branch;
use App\Models\KitchenProductionSchedule;
use App\Models\KitchenProductionScheduleItem;
use App\Models\RndBomCatalog;
use App\Models\User;
use App\Services\Kitchen\ProductionScheduleCalendarQuery;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

const KITCHEN_PAGE_PERMISSIONS = [
    'view kitchen production schedules',
    'create kitchen production schedules',
    'update kitchen production schedules',
    'cancel kitchen production schedules',
];

function productionPageUser(Branch $branch, array $permissions = KITCHEN_PAGE_PERMISSIONS): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo($permissions);
    $user->syncBranchAccess([$branch->id], $branch->id);

    return $user->refresh();
}

function pageAssembly(array $overrides = []): RndBomCatalog
{
    return RndBomCatalog::factory()->create($overrides);
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->travelTo('2026-10-14 10:00:00');
    $this->branch = Branch::factory()->kitchen()->create(['name' => 'Semarang', 'is_active' => true]);
    $this->user = productionPageUser($this->branch);
    $this->actingAs($this->user);
});

it('opens for authorized users and keeps the Kitchen sidebar group expanded', function () {
    $this->user->givePermissionTo('access backoffice');

    $this->get(KitchenProductionSchedulePage::getUrl())
        ->assertOk()
        ->assertSee('Kalender Produksi')
        ->assertSee('openGroups: ["kitchen"]', false)
        ->assertDontSee('KITCHEN_BOM_CATALOG_MAX_AGE_HOURS')
        ->assertDontSee('Publikasikan');
});

it('forbids users without view permission and rechecks access on Livewire requests', function () {
    $page = Livewire::test(KitchenProductionSchedulePage::class);
    $this->user->revokePermissionTo('view kitchen production schedules');

    $page->call('nextMonth')->assertForbidden();
});

it('uses the complete visible Monday-to-Sunday range', function () {
    [$start, $end] = app(ProductionScheduleCalendarQuery::class)->visibleRange(CarbonImmutable::parse('2026-10-01'));

    expect([$start->toDateString(), $end->toDateString()])->toBe(['2026-09-28', '2026-11-01']);
});

it('scopes schedules and Branch filters to accessible Branches', function () {
    $own = KitchenProductionSchedule::factory()->forBranch($this->branch)->onDate('2026-10-05')->create();
    $other = KitchenProductionSchedule::factory()->onDate('2026-10-06')->create();
    $page = Livewire::test(KitchenProductionSchedulePage::class);
    $ids = fn () => collect($page->instance()->calendar()['weeks'])->flatten(1)->pluck('schedules')->flatten()->pluck('id')->all();

    expect($ids())->toBe([$own->id]);

    $page->set('filterBranchId', (string) $other->branch_id);
    expect($ids())->toBe([]);
});

it('lists only Branches assigned to the Kitchen Brand', function () {
    $nonKitchen = Branch::factory()->create(['name' => 'Toko Reguler', 'is_active' => true]);
    $this->user->syncBranchAccess([$this->branch->id, $nonKitchen->id], $this->branch->id);

    $page = Livewire::test(KitchenProductionSchedulePage::class);

    expect($page->instance()->schedulableBranches()->pluck('id')->all())->toBe([$this->branch->id])
        ->and($page->instance()->filterBranches()->pluck('id')->all())->toBe([$this->branch->id]);
});

it('lets all-Branch users use every active Branch without a Kitchen Brand assignment', function () {
    $nonKitchen = Branch::factory()->create(['name' => 'Central Production', 'is_active' => true]);
    $this->user->forceFill(['access_all_branches' => true])->save();

    $page = Livewire::test(KitchenProductionSchedulePage::class);

    expect($page->instance()->schedulableBranches()->pluck('id')->all())
        ->toContain($this->branch->id, $nonKitchen->id);
});

it('shows BOM counts without summing mixed units in desktop and mobile data', function () {
    $schedule = KitchenProductionSchedule::factory()->forBranch($this->branch)->onDate('2026-10-08')->create();
    KitchenProductionScheduleItem::factory()->for($schedule, 'schedule')->create(['sequence' => 1, 'uom_name' => 'KG', 'planned_quantity' => '3']);
    KitchenProductionScheduleItem::factory()->for($schedule, 'schedule')->create(['sequence' => 2, 'uom_name' => 'PCS', 'planned_quantity' => '40']);

    $page = Livewire::test(KitchenProductionSchedulePage::class)->assertSee('2 BOM Assembly')->assertDontSee('43');
    $calendar = $page->instance()->calendar();

    expect(collect($calendar['agenda'])->pluck('schedules')->flatten()->pluck('id')->all())->toBe([$schedule->id]);
});

it('keeps calendar query count flat and excludes BOM detail JSON', function () {
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

    KitchenProductionSchedule::factory()->forBranch($this->branch)->onDate('2026-10-02')->has(KitchenProductionScheduleItem::factory(), 'items')->create();
    [$few] = $count();

    foreach (range(3, 20) as $day) {
        KitchenProductionSchedule::factory()->forBranch($this->branch)->onDate(sprintf('2026-10-%02d', $day))->create();
    }

    [$many, $sql] = $count();

    expect($many)->toBeLessThanOrEqual($few + 1)->and($sql)->not->toContain('detail_snapshot');
});

it('creates a scheduled plan directly from the editor', function () {
    $bom = pageAssembly();

    Livewire::test(KitchenProductionSchedulePage::class)
        ->call('openCreate', '2026-10-21')
        ->call('openPicker')
        ->call('togglePick', $bom->id)
        ->call('addPicked')
        ->set('form.items.0.planned_quantity', '12,5')
        ->call('saveSchedule')
        ->assertHasNoErrors()
        ->assertSet('modal', null);

    expect(KitchenProductionSchedule::sole()->status)->toBe(KitchenProductionScheduleStatus::Scheduled)
        ->and(KitchenProductionSchedule::sole()->items()->sole()->planned_quantity)->toBe('12.5000');
});

it('paginates and searches only eligible Assembly BOMs', function () {
    $assemblies = collect(range(1, 12))->map(fn (int $n) => pageAssembly(['bom_name' => sprintf('Adonan %02d', $n)]));
    pageAssembly(['bom_name' => 'Menu Palsu', 'bom_type_id' => 3, 'bom_type_name' => 'Assembly']);
    pageAssembly(['bom_name' => 'Adonan Nonaktif', 'is_active' => false]);

    $page = Livewire::test(KitchenProductionSchedulePage::class)->call('openCreate')->call('openPicker');

    expect($page->instance()->pickerResults()->total())->toBe(12)
        ->and($page->instance()->pickerResults()->count())->toBe(KitchenProductionSchedulePage::PICKER_PAGE_SIZE);

    $page->set('pickerSearch', 'Adonan 12');
    expect($page->instance()->pickerResults()->pluck('id')->all())->toBe([$assemblies[11]->id]);
});

it('edits scheduled plans and cancels them without deleting items', function () {
    $bom = pageAssembly();
    $schedule = KitchenProductionSchedule::factory()->forBranch($this->branch)->onDate('2026-10-22')->create();
    KitchenProductionScheduleItem::factory()->for($schedule, 'schedule')->assemblyItem($bom)->create(['sequence' => 1]);

    Livewire::test(KitchenProductionSchedulePage::class)
        ->call('openEdit', $schedule->id)
        ->set('form.notes', 'Produksi pagi')
        ->call('saveSchedule')
        ->assertHasNoErrors()
        ->call('openDetail', $schedule->id)
        ->call('showDetailStep', 'cancel')
        ->set('cancelReason', 'Oven rusak.')
        ->call('confirmCancel')
        ->assertHasNoErrors()
        ->assertSee('Oven rusak.');

    expect($schedule->refresh()->status)->toBe(KitchenProductionScheduleStatus::Cancelled)
        ->and($schedule->items()->count())->toBe(1);
});

it('rejects direct mutation calls without permission', function () {
    $viewer = productionPageUser($this->branch, ['view kitchen production schedules']);
    $schedule = KitchenProductionSchedule::factory()->forBranch($this->branch)->onDate('2026-10-22')->create();
    $this->actingAs($viewer);

    Livewire::test(KitchenProductionSchedulePage::class)->call('openCreate')->assertForbidden();
    Livewire::test(KitchenProductionSchedulePage::class)
        ->set('detailId', $schedule->id)
        ->set('modal', 'detail')
        ->set('cancelReason', 'Batal')
        ->call('confirmCancel')
        ->assertHasErrors('cancelReason');
});
