<?php

use App\Filament\Helpdesk\Pages\BasketSizePage;
use App\Filament\Helpdesk\Resources\Branches\Pages\CreateBranch;
use App\Filament\Helpdesk\Resources\Branches\Pages\EditBranch;
use App\Filament\Helpdesk\Resources\Branches\Pages\ListBranches;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    config()->set('esb.base_url', 'https://esb.test');
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->branchA = Branch::factory()->create(['name' => 'Kitchen Jateng', 'address' => 'Jl. Semarang']);
    $this->branchB = Branch::factory()->create(['name' => 'Kitchen Jakarta', 'address' => 'Jl. Jakarta']);
    foreach ([$this->branchA, $this->branchB] as $index => $branch) {
        $branch->esbCodes()->create(['esb_branch_code' => 'BPL'.$index, 'esb_comcode' => 'BLO16', 'label' => 'DINE IN', 'is_active' => true]);
    }

    $this->supervisor = User::factory()->create(['is_active' => true]);
    $this->supervisor->assignRole('SUPERVISOR_STORE');
    $this->supervisor->syncBranchAccess([$this->branchA->id], $this->branchA->id);
    $this->actingAs($this->supervisor);
    $this->travelTo('2026-09-12 20:00:00');
});

it('gives the Supervisor Store role basket size and branch shift access', function () {
    expect(Role::findByName('SUPERVISOR_STORE')->hasAllPermissions([
        'view basket sizes', 'recalculate basket sizes', 'view branches', 'edit branch shifts',
    ]))->toBeTrue()
        ->and($this->supervisor->can('edit branches'))->toBeFalse()
        ->and($this->supervisor->can('create branches'))->toBeFalse()
        ->and($this->supervisor->can('delete branches'))->toBeFalse();
});

it('shows a supervisor only the basket size of the branches they can access', function () {
    $recordA = submittedShift($this->branchA, '2026-09-10', '08:00', '17:00');
    $recordB = submittedShift($this->branchB, '2026-09-10', '08:00', '17:00');
    $nameA = $recordA->employeeRecords()->firstOrFail()->employee_name;
    $nameB = $recordB->employeeRecords()->firstOrFail()->employee_name;

    $page = Livewire::test(BasketSizePage::class)
        ->set('dateFrom', '2026-09-01')
        ->set('dateTo', '2026-09-12')
        ->assertSee($nameA)
        ->assertDontSee($nameB);

    expect($page->instance()->branches()->pluck('name')->all())->toBe(['Kitchen Jateng']);

    $page->set('branchId', $this->branchB->id)
        ->assertDontSee($nameA)
        ->assertDontSee($nameB);

    $page->set('branchId', null)
        ->set('employee', $recordB->employeeRecords()->firstOrFail()->employee_id);
    expect($page->instance()->history())->toBeEmpty();

    $this->supervisor->syncBranchAccess([$this->branchA->id, $this->branchB->id], $this->branchA->id);
    Livewire::test(BasketSizePage::class)
        ->set('dateFrom', '2026-09-01')
        ->set('dateTo', '2026-09-12')
        ->assertSee($nameA)
        ->assertSee($nameB);
});

it('lets a supervisor recalculate basket size only for their own branches', function () {
    $recordA = submittedShift($this->branchA, '2026-09-10', '08:00', '17:00');
    $recordB = submittedShift($this->branchB, '2026-09-10', '08:00', '17:00');
    fakeEsbSales([finalizeSale('A', '2026-09-10 10:00:00', 10, 100_000)]);

    runRecalculation(Livewire::test(BasketSizePage::class)
        ->set('dateFrom', '2026-09-01')
        ->set('dateTo', '2026-09-12')
        ->assertSee('Hitung Ulang')
        ->assertSee('1 shift pada filter ini'))
        ->assertNotified('Hitung ulang basket size selesai');

    expect($recordA->fresh()->isFinal())->toBeTrue()
        ->and($recordB->fresh()->isFinal())->toBeFalse();
    Http::assertSentCount(1);
});

it('lists only the accessible branches for a supervisor and hides the actions they may not use', function () {
    Livewire::test(ListBranches::class)
        ->assertCanSeeTableRecords([$this->branchA])
        ->assertCanNotSeeTableRecords([$this->branchB])
        ->assertTableActionVisible('edit', $this->branchA)
        ->assertTableActionHidden('toggle_active', $this->branchA)
        ->assertTableActionHidden('delete', $this->branchA);

    Livewire::test(CreateBranch::class)->assertForbidden();
});

it('only lets a supervisor edit the branches they can access', function () {
    Livewire::test(EditBranch::class, ['record' => $this->branchA->id])->assertSuccessful();

    expect(fn () => Livewire::test(EditBranch::class, ['record' => $this->branchB->id]))->toThrow(ModelNotFoundException::class);

    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo('view branches');
    $viewer->syncBranchAccess([$this->branchA->id], $this->branchA->id);
    $this->actingAs($viewer);

    Livewire::test(EditBranch::class, ['record' => $this->branchA->id])->assertForbidden();
});

it('shows a supervisor only the shift group of the branch form', function () {
    Livewire::test(EditBranch::class, ['record' => $this->branchA->id])
        ->assertSee('Kitchen Jateng')
        ->assertSee('Ubah jam shift branch ini')
        ->assertSee('Shift Basket Size')
        ->assertSee('Simpan Perubahan')
        ->assertDontSee('Informasi Branch')
        ->assertDontSee('Kode ESB')
        ->assertDontSee('Lokasi Absen')
        ->assertDontSee('Wajib Validasi Lokasi');
});

it('lets a supervisor change the shifts of their branch and nothing else', function () {
    $shift = $this->branchA->salesShifts()->create(['shift_number' => 1, 'name' => 'Shift 1', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);

    $edit = Livewire::test(EditBranch::class, ['record' => $this->branchA->id]);
    $state = collect($edit->get('data.salesShifts'))
        ->map(fn (array $item): array => [...$item, 'start_time' => '08:00', 'end_time' => '17:00'])
        ->all();

    $edit->fillForm(['salesShifts' => $state])
        ->set('data.name', 'Diretas')
        ->set('data.address', 'Alamat palsu')
        ->set('data.is_active', false)
        ->set('data.location_required', true)
        ->set('data.esbCodes', [])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($shift->fresh()->start_time)->toStartWith('08:00')
        ->and($shift->fresh()->end_time)->toStartWith('17:00');

    $branch = $this->branchA->fresh();
    expect($branch->name)->toBe('Kitchen Jateng')
        ->and($branch->address)->toBe('Jl. Semarang')
        ->and($branch->is_active)->toBeTrue()
        ->and($branch->location_required)->toBeFalse()
        ->and($branch->esbCodes()->count())->toBe(1);
});

it('keeps full branch editing and every branch for users with the edit branches permission', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    Livewire::test(ListBranches::class)
        ->assertCanSeeTableRecords([$this->branchA, $this->branchB])
        ->assertTableActionVisible('toggle_active', $this->branchB);

    Livewire::test(EditBranch::class, ['record' => $this->branchB->id])
        ->assertSee('Informasi Branch')
        ->assertSee('Kode ESB')
        ->assertSee('Lokasi Absen');
});

it('grants the supervisor permissions on deploy without touching other roles', function () {
    $role = Role::findByName('SUPERVISOR_STORE');
    $role->revokePermissionTo(['view basket sizes', 'recalculate basket sizes', 'view branches', 'edit branch shifts']);
    Permission::where('name', 'edit branch shifts')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $otherRole = Role::findByName('FINANCE_STAFF');
    $before = $otherRole->permissions()->count();

    (require database_path('migrations/2026_09_21_135003_grant_basket_size_and_shift_access_to_supervisor_store.php'))->up();

    expect(Role::findByName('SUPERVISOR_STORE')->hasAllPermissions(['view basket sizes', 'recalculate basket sizes', 'view branches', 'edit branch shifts']))->toBeTrue()
        ->and($otherRole->fresh()->permissions()->count())->toBe($before);
});

it('ignores queued shifts outside the accessible branches when a supervisor tampers with the queue', function () {
    $recordA = submittedShift($this->branchA, '2026-09-10', '08:00', '17:00');
    $recordB = submittedShift($this->branchB, '2026-09-10', '08:00', '17:00');
    fakeEsbSales([finalizeSale('A', '2026-09-10 10:00:00', 10, 100_000)]);

    Livewire::test(BasketSizePage::class)
        ->set('dateFrom', '2026-09-01')
        ->set('dateTo', '2026-09-12')
        ->set('recalculationQueue', [$recordB->id, $recordA->id])
        ->set('recalculationTotal', 2)
        ->call('processRecalculationBatch');

    expect($recordA->fresh()->isFinal())->toBeTrue()
        ->and($recordB->fresh()->isFinal())->toBeFalse();
    Http::assertSentCount(1);
});
