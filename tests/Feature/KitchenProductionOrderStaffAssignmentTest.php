<?php

use App\Actions\Kitchen\AssignProductionOrderStaffAction;
use App\Actions\Kitchen\StoreProductionOrderDetailAction;
use App\Enums\KitchenProductionOrderStaffAssignmentEventType;
use App\Filament\Helpdesk\Pages\KitchenProductionSchedulePage;
use App\Models\Branch;
use App\Models\BranchEsbCode;
use App\Models\Employee;
use App\Models\EsbProductionOrder;
use App\Models\EsbProductionOrderDetail;
use App\Models\KitchenProductionOrderStaffAssignment;
use App\Models\KitchenProductionOrderStaffAssignmentEvent;
use App\Models\User;
use App\Services\Kitchen\EsbProductionOrderNormalizer;
use App\Services\Kitchen\ProductionOrderDetailBuilder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
 * Staff Produksi per Production Order detail (docs/products/kitchen/production-staff-assignment/prd.md §21).
 */

function staffAssigner(Branch $branch, array $permissions = ['view kitchen production schedules', 'assign kitchen production order staff']): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo($permissions);
    $user->syncBranchAccess([$branch->id], $branch->id);

    return $user->refresh();
}

function staffMapping(Branch $branch): BranchEsbCode
{
    return $branch->esbCodes()->firstOrCreate(
        ['esb_comcode' => 'BLSS', 'esb_branch_code' => 'K'.$branch->id],
        ['esb_branch_id' => 700 + $branch->id, 'is_active' => true],
    );
}

function staffDetail(Branch $branch, array $detail = [], array $order = []): EsbProductionOrderDetail
{
    $productionOrder = EsbProductionOrder::factory()->forMapping(staffMapping($branch))->create(['production_order_date' => '2026-10-14', ...$order]);

    return EsbProductionOrderDetail::factory()->for($productionOrder, 'order')->create($detail);
}

function assignStaff(User $user, EsbProductionOrderDetail $detail, array $employeeIds, ?array $expected = null): array
{
    return app(AssignProductionOrderStaffAction::class)->execute($user, $detail->id, $employeeIds, $expected);
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    Http::preventStrayRequests();
    $this->travelTo('2026-10-14 10:00:00');
    $this->branch = Branch::factory()->kitchen()->create(['name' => 'Kitchen Semarang', 'is_active' => true]);
    $this->detail = staffDetail($this->branch, ['bom_name' => 'Adonan Roti Contoh', 'product_name' => 'Roti Contoh']);
    $this->order = $this->detail->order;
    $this->user = staffAssigner($this->branch);
    $this->employees = Employee::factory()->count(3)->sequence(
        ['name' => 'Ani Contoh', 'employee_code' => 'EMP001', 'position' => 'Cook'],
        ['name' => 'Budi Contoh', 'employee_code' => 'EMP002', 'position' => 'Baker'],
        ['name' => 'Citra Contoh', 'employee_code' => 'EMP003', 'position' => 'Helper'],
    )->create(['branch_id' => $this->branch->id]);
    $this->actingAs($this->user);
});

// ── Phase 1: schema, model, permission, Policy ─────────────────────────────

it('creates additive tables with a unique detail + employee key', function () {
    expect(Schema::hasTable('kitchen_production_order_staff_assignments'))->toBeTrue()
        ->and(Schema::hasColumns('kitchen_production_order_staff_assignments', ['esb_production_order_detail_id', 'employee_id', 'employee_code', 'employee_name', 'employee_position', 'branch_id', 'branch_name', 'assigned_by', 'assigned_at']))->toBeTrue()
        ->and(Schema::hasColumns('kitchen_production_order_staff_assignment_events', ['esb_production_order_id', 'esb_production_order_detail_id', 'event', 'employee_id', 'employee_code', 'employee_name', 'branch_name', 'production_order_num', 'bom_name', 'product_name', 'actor_id', 'metadata', 'created_at']))->toBeTrue()
        ->and(Schema::hasColumn('esb_production_order_details', 'staff_count'))->toBeFalse();

    $employee = $this->employees[0];
    KitchenProductionOrderStaffAssignment::factory()->forEmployee($employee)->create(['esb_production_order_detail_id' => $this->detail->id]);

    expect(fn () => KitchenProductionOrderStaffAssignment::factory()->forEmployee($employee)->create(['esb_production_order_detail_id' => $this->detail->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('relates assignments to detail, employee, Branch, and actor with casts', function () {
    $assignment = KitchenProductionOrderStaffAssignment::factory()->forEmployee($this->employees[0])->create([
        'esb_production_order_detail_id' => $this->detail->id,
        'assigned_by' => $this->user->id,
    ]);

    expect($this->detail->staffAssignments()->sole()->is($assignment))->toBeTrue()
        ->and($this->employees[0]->productionOrderStaffAssignments()->sole()->is($assignment))->toBeTrue()
        ->and($assignment->detail->is($this->detail))->toBeTrue()
        ->and($assignment->branch->is($this->branch))->toBeTrue()
        ->and($assignment->assignedBy->is($this->user))->toBeTrue()
        ->and($assignment->assigned_at)->toBeInstanceOf(DateTimeInterface::class);
});

it('keeps audit events append-only', function () {
    assignStaff($this->user, $this->detail, [$this->employees[0]->id]);
    $event = KitchenProductionOrderStaffAssignmentEvent::query()->sole();

    expect($event->event)->toBe(KitchenProductionOrderStaffAssignmentEventType::Assigned)
        ->and($event->metadata)->toBe(['detail_finished' => false])
        ->and(fn () => $event->update(['employee_name' => 'Diubah']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class)
        ->and(KitchenProductionOrderStaffAssignmentEvent::query()->sole()->employee_name)->toBe('Ani Contoh');
});

it('registers the permission without granting it to operational roles', function () {
    expect(config('permissions.Kitchen.Jadwal Produksi'))->toContain('assign kitchen production order staff')
        ->and(Permission::query()->where('name', 'assign kitchen production order staff')->exists())->toBeTrue()
        ->and(Role::query()->where('name', '!=', 'SUPERADMIN')->get()->filter(fn (Role $role): bool => $role->hasPermissionTo('assign kitchen production order staff'))->all())->toBe([]);
});

it('authorizes assignment per Policy matrix', function (Closure $arrange, bool $allowed) {
    $user = User::factory()->create(['is_active' => true]);
    $order = $arrange->call($this, $user);

    expect($user->refresh()->can('assignStaff', $order->refresh()))->toBe($allowed);
})->with([
    'viewer only' => [function (User $user) {
        $user->givePermissionTo('view kitchen production schedules');
        $user->syncBranchAccess([$this->branch->id], $this->branch->id);

        return $this->order;
    }, false],
    'assigner' => [function (User $user) {
        $user->givePermissionTo(['view kitchen production schedules', 'assign kitchen production order staff']);
        $user->syncBranchAccess([$this->branch->id], $this->branch->id);

        return $this->order;
    }, true],
    'assign without view' => [function (User $user) {
        $user->givePermissionTo('assign kitchen production order staff');
        $user->syncBranchAccess([$this->branch->id], $this->branch->id);

        return $this->order;
    }, false],
    'assigner of another Kitchen Branch' => [function (User $user) {
        $user->givePermissionTo(['view kitchen production schedules', 'assign kitchen production order staff']);
        $other = Branch::factory()->kitchen()->create(['is_active' => true]);
        $user->syncBranchAccess([$other->id], $other->id);

        return $this->order;
    }, false],
    'view-all assigner' => [function (User $user) {
        $user->givePermissionTo(['view kitchen production schedules', 'view all branch kitchen production schedules', 'assign kitchen production order staff']);

        return $this->order;
    }, true],
    'view-all without assign' => [function (User $user) {
        $user->givePermissionTo(['view kitchen production schedules', 'view all branch kitchen production schedules']);

        return $this->order;
    }, false],
    'all-branch assigner on a non-Kitchen Branch' => [function (User $user) {
        $user->givePermissionTo(['view kitchen production schedules', 'assign kitchen production order staff']);
        $user->forceFill(['access_all_branches' => true])->save();

        return staffDetail(Branch::factory()->create(['is_active' => true]))->order;
    }, false],
    'assigner on an inactive Kitchen Branch' => [function (User $user) {
        $user->givePermissionTo(['view kitchen production schedules', 'assign kitchen production order staff']);
        $user->syncBranchAccess([$this->branch->id], $this->branch->id);
        $this->branch->update(['is_active' => false]);

        return $this->order;
    }, false],
]);

// ── Phase 2: Action ────────────────────────────────────────────────────────

it('assigns one and several Staff Produksi with an identity snapshot and audit', function () {
    [$ani, $budi] = [$this->employees[0], $this->employees[1]];

    expect(assignStaff($this->user, $this->detail, [$ani->id]))->toBe(['assigned' => 1, 'unassigned' => 0])
        ->and(assignStaff($this->user, $this->detail, [$ani->id, (string) $budi->id]))->toBe(['assigned' => 1, 'unassigned' => 0]);

    $rows = $this->detail->staffAssignments()->orderBy('employee_name')->get();
    expect($rows->pluck('employee_id')->all())->toBe([$ani->id, $budi->id])
        ->and($rows[0]->only(['employee_code', 'employee_name', 'employee_position', 'branch_id', 'branch_name', 'assigned_by']))
        ->toBe(['employee_code' => 'EMP001', 'employee_name' => 'Ani Contoh', 'employee_position' => 'Cook', 'branch_id' => $this->branch->id, 'branch_name' => 'Kitchen Semarang', 'assigned_by' => $this->user->id]);

    $event = KitchenProductionOrderStaffAssignmentEvent::query()->where('employee_id', $budi->id)->sole();
    expect($event->only(['esb_production_order_id', 'esb_production_order_detail_id', 'production_order_num', 'bom_name', 'product_name', 'branch_name', 'actor_id']))
        ->toBe([
            'esb_production_order_id' => $this->order->id,
            'esb_production_order_detail_id' => $this->detail->id,
            'production_order_num' => $this->order->production_order_num,
            'bom_name' => 'Adonan Roti Contoh',
            'product_name' => 'Roti Contoh',
            'branch_name' => 'Kitchen Semarang',
            'actor_id' => $this->user->id,
        ])
        ->and(KitchenProductionOrderStaffAssignmentEvent::query()->count())->toBe(2);
});

it('replaces the selection by diff and keeps retained rows untouched', function () {
    [$ani, $budi, $citra] = $this->employees->all();
    assignStaff($this->user, $this->detail, [$ani->id, $budi->id]);
    $retained = $this->detail->staffAssignments()->where('employee_id', $ani->id)->sole();

    expect(assignStaff($this->user, $this->detail, [$ani->id, $citra->id]))->toBe(['assigned' => 1, 'unassigned' => 1])
        ->and($this->detail->staffAssignments()->where('employee_id', $ani->id)->sole()->id)->toBe($retained->id)
        ->and($this->detail->staffAssignments()->pluck('employee_id')->sort()->values()->all())->toBe([$ani->id, $citra->id])
        ->and(KitchenProductionOrderStaffAssignmentEvent::query()->where('event', 'unassigned')->sole()->employee_id)->toBe($budi->id);
});

it('releases part and then all of the staff', function () {
    [$ani, $budi] = [$this->employees[0], $this->employees[1]];
    assignStaff($this->user, $this->detail, [$ani->id, $budi->id]);

    expect(assignStaff($this->user, $this->detail, [$ani->id]))->toBe(['assigned' => 0, 'unassigned' => 1])
        ->and(assignStaff($this->user, $this->detail, []))->toBe(['assigned' => 0, 'unassigned' => 1])
        ->and($this->detail->staffAssignments()->count())->toBe(0)
        ->and(KitchenProductionOrderStaffAssignmentEvent::query()->where('event', 'unassigned')->count())->toBe(2);
});

it('treats a repeated selection as a no-op without rows or audit', function () {
    assignStaff($this->user, $this->detail, [$this->employees[0]->id]);
    $before = [KitchenProductionOrderStaffAssignment::query()->sole()->updated_at, KitchenProductionOrderStaffAssignmentEvent::query()->count()];

    $this->travel(5)->minutes();

    expect(assignStaff($this->user, $this->detail, [$this->employees[0]->id]))->toBe(['assigned' => 0, 'unassigned' => 0])
        ->and(assignStaff($this->user, $this->detail, [(string) $this->employees[0]->id]))->toBe(['assigned' => 0, 'unassigned' => 0])
        ->and([KitchenProductionOrderStaffAssignment::query()->sole()->updated_at, KitchenProductionOrderStaffAssignmentEvent::query()->count()])->toEqual($before)
        ->and(assignStaff($this->user, staffDetail($this->branch), []))->toBe(['assigned' => 0, 'unassigned' => 0]);
});

it('rejects malformed selections without writing anything', function (Closure $selection) {
    expect(fn () => assignStaff($this->user, $this->detail, $selection->call($this)))->toThrow(ValidationException::class)
        ->and(KitchenProductionOrderStaffAssignment::query()->count())->toBe(0)
        ->and(KitchenProductionOrderStaffAssignmentEvent::query()->count())->toBe(0);
})->with([
    'duplicate' => [fn () => [$this->employees[0]->id, $this->employees[0]->id]],
    'non-integer' => [fn () => ['abc']],
    'negative' => [fn () => [-1]],
    'oversized' => [fn () => range(1, AssignProductionOrderStaffAction::MAX_STAFF_PER_DETAIL + 1)],
]);

it('rolls back the whole change when one new employee is not eligible (OD-1)', function (Closure $invalid) {
    [$ani, $budi] = [$this->employees[0], $this->employees[1]];
    assignStaff($this->user, $this->detail, [$ani->id]);

    expect(fn () => assignStaff($this->user, $this->detail, [$budi->id, $invalid->call($this)]))->toThrow(ValidationException::class)
        ->and($this->detail->staffAssignments()->pluck('employee_id')->all())->toBe([$ani->id])
        ->and(KitchenProductionOrderStaffAssignmentEvent::query()->count())->toBe(1);
})->with([
    'inactive' => [fn () => Employee::factory()->create(['branch_id' => $this->branch->id, 'is_active' => false])->id],
    'other Kitchen Branch' => [fn () => Employee::factory()->create(['branch_id' => Branch::factory()->kitchen()->create()->id])->id],
    'missing' => [fn () => 999999],
]);

it('keeps an existing staff member who became ineligible but refuses to add them back', function () {
    $ani = $this->employees[0];
    assignStaff($this->user, $this->detail, [$ani->id, $this->employees[1]->id]);
    $ani->update(['is_active' => false]);

    expect(assignStaff($this->user, $this->detail, [$ani->id]))->toBe(['assigned' => 0, 'unassigned' => 1])
        ->and(assignStaff($this->user, $this->detail, []))->toBe(['assigned' => 0, 'unassigned' => 1])
        ->and(fn () => assignStaff($this->user, $this->detail, [$ani->id]))->toThrow(ValidationException::class);
});

it('refuses forged, foreign, non-Kitchen, and unsynced details', function () {
    $foreign = staffDetail(Branch::factory()->kitchen()->create(['is_active' => true]));
    $store = staffDetail(Branch::factory()->create(['is_active' => true]));
    $pending = EsbProductionOrderDetail::factory()->for(EsbProductionOrder::factory()->forMapping(staffMapping($this->branch))->withoutDetails(), 'order')->create();
    $superadmin = User::factory()->create(['is_active' => true]);
    $superadmin->assignRole('SUPERADMIN');

    expect(fn () => assignStaff($this->user, $foreign, []))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AssignProductionOrderStaffAction::class)->execute($this->user, 999999, []))->toThrow(ModelNotFoundException::class)
        ->and(fn () => assignStaff($superadmin, $store, []))->toThrow(ModelNotFoundException::class)
        ->and(fn () => assignStaff($this->user, $pending, [$this->employees[0]->id]))->toThrow(ValidationException::class)
        ->and(fn () => assignStaff(staffAssigner($this->branch, ['view kitchen production schedules']), $this->detail, [$this->employees[0]->id]))->toThrow(AuthorizationException::class)
        ->and(KitchenProductionOrderStaffAssignment::query()->count())->toBe(0);

    $this->branch->update(['is_active' => false]);
    expect(fn () => assignStaff($superadmin, $this->detail, [$this->employees[0]->id]))->toThrow(AuthorizationException::class);
});

it('refuses a save based on a stale view so concurrent edits are not lost', function () {
    [$ani, $budi] = [$this->employees[0], $this->employees[1]];
    $other = staffAssigner($this->branch);
    $seenByBoth = [];

    assignStaff($other, $this->detail, [$ani->id], $seenByBoth);

    expect(fn () => assignStaff($this->user, $this->detail, [$budi->id], $seenByBoth))->toThrow(ValidationException::class)
        ->and($this->detail->staffAssignments()->pluck('employee_id')->all())->toBe([$ani->id]);

    $fresh = $this->detail->staffAssignments()->pluck('id')->all();
    expect(assignStaff($this->user, $this->detail, [$ani->id, $budi->id], $fresh))->toBe(['assigned' => 1, 'unassigned' => 0]);
});

it('never writes duplicates when two saves without a stale check send the same selection', function () {
    $other = staffAssigner($this->branch);

    assignStaff($other, $this->detail, [$this->employees[0]->id]);
    assignStaff($this->user, $this->detail, [$this->employees[0]->id]);

    expect(KitchenProductionOrderStaffAssignment::query()->count())->toBe(1)
        ->and(KitchenProductionOrderStaffAssignmentEvent::query()->count())->toBe(1);
});

it('allows correcting a finished detail and records it in the audit (OD-2)', function () {
    $this->detail->update(['is_finished' => true]);

    assignStaff($this->user, $this->detail, [$this->employees[0]->id]);

    expect(KitchenProductionOrderStaffAssignmentEvent::query()->sole()->metadata)->toBe(['detail_finished' => true]);
});

it('keeps history readable when the employee is deleted and releases the orphan on the next save', function () {
    $ani = $this->employees[0];
    assignStaff($this->user, $this->detail, [$ani->id]);
    $ani->delete();

    $orphan = $this->detail->staffAssignments()->with('employee')->sole();
    expect($orphan->employee_id)->toBeNull()
        ->and($orphan->employee_name)->toBe('Ani Contoh')
        ->and($orphan->historyWarning($this->branch->id))->toBe('Data employee dihapus')
        ->and(KitchenProductionOrderStaffAssignmentEvent::query()->sole()->employee_name)->toBe('Ani Contoh');

    expect(assignStaff($this->user, $this->detail, [$this->employees[1]->id]))->toBe(['assigned' => 1, 'unassigned' => 1])
        ->and(KitchenProductionOrderStaffAssignmentEvent::query()->where('event', 'unassigned')->sole()->metadata)->toBe(['detail_finished' => false, 'reason' => 'employee_deleted']);
});

it('never calls ESB, queues nothing, and leaves the snapshot unchanged', function () {
    Queue::fake();
    $snapshot = $this->detail->fresh()->getAttributes();
    $orderSnapshot = $this->order->fresh()->getAttributes();

    assignStaff($this->user, $this->detail, [$this->employees[0]->id]);

    Http::assertNothingSent();
    Queue::assertNothingPushed();
    expect($this->detail->fresh()->getAttributes())->toBe($snapshot)
        ->and($this->order->fresh()->getAttributes())->toBe($orderSnapshot);
});

// ── Sync regression ────────────────────────────────────────────────────────

it('keeps assignments when the same detail is synced again, changed, or missing from the response', function () {
    $order = EsbProductionOrder::factory()->forMapping(staffMapping($this->branch))->withoutDetails()->create(['production_order_num' => 'PO-0001']);
    $store = fn (array $details) => app(StoreProductionOrderDetailAction::class)->execute($order, app(EsbProductionOrderNormalizer::class)->order(esbProductionOrderDetailResponse([], $details)['result']));

    $store([esbProductionOrderDetailRow(), esbProductionOrderDetailRow(['ID' => 9002])]);
    $detail = $order->details()->where('external_detail_id', '9001')->sole();
    assignStaff($this->user, $detail, [$this->employees[0]->id, $this->employees[1]->id]);

    $store([esbProductionOrderDetailRow(['productionOrderQty' => '20.0000', 'flagFinished' => 1]), esbProductionOrderDetailRow(['ID' => 9002])]);
    $store([esbProductionOrderDetailRow(['ID' => 9002])]);

    expect($order->details()->where('external_detail_id', '9001')->sole()->id)->toBe($detail->id)
        ->and($detail->fresh()->quantity)->toBe('20.0000')
        ->and($detail->staffAssignments()->count())->toBe(2)
        ->and(KitchenProductionOrderStaffAssignmentEvent::query()->count())->toBe(2);
});

// ── Phase 3: UI ────────────────────────────────────────────────────────────

it('shows the Staff Produksi section and empty state to viewers without the assign button', function () {
    $this->actingAs(staffAssigner($this->branch, ['view kitchen production schedules']));

    Livewire::test(KitchenProductionSchedulePage::class)
        ->call('openProductionOrder', $this->order->id)
        ->assertSee('Data Production Order berasal dari ESB dan hanya dapat dibaca. Assignment Staff Produksi dikelola di Bloomery Office.')
        ->assertSee('Staff Produksi')
        ->assertSee('Belum ada Staff Produksi yang ditugaskan.')
        ->assertSee('0 dari 1 detail sudah memiliki Staff Produksi')
        ->assertDontSee('Atur Staff Produksi')
        ->call('openStaffPicker', $this->detail->id)
        ->assertSet('staffDetailId', null);
});

it('lets an assigner pick, search, and save several Staff Produksi from the detail', function () {
    $this->employees->push(Employee::factory()->create(['branch_id' => $this->branch->id, 'is_active' => false, 'name' => 'Dodi Nonaktif']));
    $this->employees->push(Employee::factory()->create(['branch_id' => Branch::factory()->kitchen()->create()->id, 'name' => 'Eka Cabang Lain']));
    assignStaff($this->user, $this->detail, [$this->employees[0]->id]);

    $page = Livewire::test(KitchenProductionSchedulePage::class)
        ->call('openProductionOrder', $this->order->id)
        ->assertSee('Atur Staff Produksi')
        ->assertSee('Ani Contoh')
        ->call('openStaffPicker', $this->detail->id)
        ->assertSet('staffDetailId', $this->detail->id)
        ->assertSet('selectedStaffIds', [(string) $this->employees[0]->id])
        ->assertSee('Pilih Staff Produksi')
        ->assertSee('Budi Contoh')
        ->assertSee('Citra Contoh')
        ->assertDontSee('Dodi Nonaktif')
        ->assertDontSee('Eka Cabang Lain')
        ->set('staffSearch', 'baker')
        ->assertSee('Budi Contoh')
        ->assertDontSee('Citra Contoh')
        ->set('selectedStaffIds', [(string) $this->employees[0]->id, (string) $this->employees[1]->id])
        ->call('saveStaff')
        ->assertHasNoErrors()
        ->assertSet('staffDetailId', null)
        ->assertSee('1 dari 1 detail sudah memiliki Staff Produksi');

    expect($this->detail->staffAssignments()->pluck('employee_id')->sort()->values()->all())->toBe([$this->employees[0]->id, $this->employees[1]->id]);
    $page->assertSee('Budi Contoh');
});

it('asks for confirmation before releasing all staff and before correcting a finished detail', function () {
    assignStaff($this->user, $this->detail, [$this->employees[0]->id]);
    $this->detail->update(['is_finished' => true]);

    Livewire::test(KitchenProductionSchedulePage::class)
        ->call('openProductionOrder', $this->order->id)
        ->call('openStaffPicker', $this->detail->id)
        ->assertSee('Detail ini sudah selesai di ESB.')
        ->set('selectedStaffIds', [])
        ->call('saveStaff')
        ->assertSet('staffConfirmation', ['Semua Staff Produksi pada detail ini akan dilepas.', 'Detail ini sudah selesai di ESB. Koreksi Staff Produksi tetap dicatat di riwayat perubahan.'])
        ->assertSee('Konfirmasi perubahan Staff Produksi');

    expect($this->detail->staffAssignments()->count())->toBe(1);

    Livewire::test(KitchenProductionSchedulePage::class)
        ->call('openProductionOrder', $this->order->id)
        ->call('openStaffPicker', $this->detail->id)
        ->set('selectedStaffIds', [])
        ->call('saveStaff', true)
        ->assertSet('staffDetailId', null);

    expect($this->detail->staffAssignments()->count())->toBe(0);
});

it('labels inactive, moved, and deleted staff without offering them again', function () {
    [$ani, $budi, $citra] = $this->employees->all();
    assignStaff($this->user, $this->detail, [$ani->id, $budi->id, $citra->id]);
    $ani->update(['is_active' => false]);
    $budi->update(['branch_id' => Branch::factory()->kitchen()->create()->id]);
    $citra->delete();

    Livewire::test(KitchenProductionSchedulePage::class)
        ->call('openProductionOrder', $this->order->id)
        ->assertSee('Nonaktif')
        ->assertSee('Branch berubah')
        ->assertSee('Data employee dihapus')
        ->assertSee('Citra Contoh')
        ->call('openStaffPicker', $this->detail->id)
        ->assertSet('selectedStaffIds', [(string) $ani->id, (string) $budi->id])
        ->assertSee('akan dilepas saat disimpan')
        ->assertSee('Tidak ada employee aktif lain di Branch ini.');
});

it('shows a server error in the picker and writes nothing for a forged selection', function () {
    $foreign = Employee::factory()->create(['branch_id' => Branch::factory()->kitchen()->create()->id]);

    Livewire::test(KitchenProductionSchedulePage::class)
        ->call('openProductionOrder', $this->order->id)
        ->call('openStaffPicker', $this->detail->id)
        ->set('selectedStaffIds', [(string) $foreign->id])
        ->call('saveStaff')
        ->assertHasErrors('selectedStaffIds')
        ->assertSee('Sebagian Staff Produksi yang dipilih tidak aktif atau bukan dari Branch Production Order ini.')
        ->assertSet('staffDetailId', $this->detail->id);

    expect(KitchenProductionOrderStaffAssignment::query()->count())->toBe(0);
});

it('refuses a picker for a detail outside the open order or the user scope', function () {
    $foreign = staffDetail(Branch::factory()->kitchen()->create(['is_active' => true]));
    $otherOrderDetail = staffDetail($this->branch);

    Livewire::test(KitchenProductionSchedulePage::class)
        ->call('openProductionOrder', $this->order->id)
        ->call('openStaffPicker', $foreign->id)
        ->assertSet('staffDetailId', null)
        ->call('openStaffPicker', $otherOrderDetail->id)
        ->assertSet('staffDetailId', null);
});

it('closes the picker on cancel and the whole detail on close', function () {
    Livewire::test(KitchenProductionSchedulePage::class)
        ->call('openProductionOrder', $this->order->id)
        ->call('openStaffPicker', $this->detail->id)
        ->call('closeStaffPicker')
        ->assertSet('staffDetailId', null)
        ->assertSet('productionOrderId', $this->order->id)
        ->call('openStaffPicker', $this->detail->id)
        ->call('closeModal')
        ->assertSet('staffDetailId', null)
        ->assertSet('productionOrderId', null);
});

it('shows coverage on calendar chips counting details, not employees', function () {
    EsbProductionOrderDetail::factory()->for($this->order, 'order')->create(['sequence' => 2]);
    assignStaff($this->user, $this->detail, [$this->employees[0]->id, $this->employees[1]->id]);

    $order = collect(Livewire::test(KitchenProductionSchedulePage::class)->instance()->calendar()['weeks'])
        ->flatten(1)->pluck('orders')->flatten()->sole();

    expect([$order->details_count, $order->staffed_details_count])->toBe([2, 1]);
    Livewire::test(KitchenProductionSchedulePage::class)->assertSee('Staff 1/2')->assertSee('Staff Produksi 1 dari 2 detail');
});

// ── Query count ────────────────────────────────────────────────────────────

it('keeps detail, picker, and calendar query counts flat as staff and details grow', function () {
    $count = function (Closure $callback): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    };
    $builder = app(ProductionOrderDetailBuilder::class);
    $page = Livewire::test(KitchenProductionSchedulePage::class)->instance();
    $measure = fn (): array => [
        $count(fn () => $builder->build($this->user, $this->order->id)),
        $count(fn () => $builder->staffCandidates($builder->staffPickerDetail($this->user, $this->detail->id), '')),
        $count(function () use ($page) {
            unset($page->calendar);
            $page->calendar();
        }),
    ];

    assignStaff($this->user, $this->detail, [$this->employees[0]->id]);
    $few = $measure();

    foreach (range(2, 8) as $sequence) {
        $detail = EsbProductionOrderDetail::factory()->for($this->order, 'order')->create(['sequence' => $sequence]);
        KitchenProductionOrderStaffAssignment::factory()->count(2)->create(['esb_production_order_detail_id' => $detail->id]);
    }
    Employee::factory()->count(10)->create(['branch_id' => $this->branch->id]);
    assignStaff($this->user, $this->detail, $this->employees->pluck('id')->all());

    expect($measure())->toBe($few);
});
