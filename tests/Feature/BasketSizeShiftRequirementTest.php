<?php

use App\Filament\Helpdesk\Pages\BasketSizePage;
use App\Filament\Helpdesk\Resources\Branches\BranchResource;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->travelTo('2026-09-12 20:00:00');

    $this->branchA = Branch::factory()->create(['name' => 'Kitchen Jateng', 'is_active' => true]);
    $this->branchB = Branch::factory()->create(['name' => 'Kitchen Jakarta', 'is_active' => true]);
    $this->supervisor = User::factory()->create(['is_active' => true]);
    $this->supervisor->assignRole('SUPERVISOR_STORE');
    $this->supervisor->syncBranchAccess([$this->branchA->id], $this->branchA->id);
    $this->actingAs($this->supervisor);

    $this->recordWithoutShifts = function (Branch $branch) {
        $record = submittedShift($branch, '2026-09-10', '08:00', '17:00');
        $branch->salesShifts()->delete();

        return $record;
    };
    $this->openPage = fn () => Livewire::test(BasketSizePage::class)->set('dateFrom', '2026-09-01')->set('dateTo', '2026-09-12');
});

it('keeps the basket size closed for a supervisor until their branch has shifts', function () {
    $record = ($this->recordWithoutShifts)($this->branchA);
    $employee = $record->employeeRecords()->firstOrFail();

    $page = ($this->openPage)()
        ->assertSee('Isi Shift Basket Size terlebih dahulu')
        ->assertSee('Kitchen Jateng')
        ->assertSeeHtml(BranchResource::getUrl('edit', ['record' => $this->branchA->id]))
        ->assertSee('Isi Shift')
        ->assertDontSee($employee->employee_name)
        ->assertDontSee('Hitung Ulang');

    expect($page->instance()->mustFillShiftsFirst())->toBeTrue()
        ->and($page->instance()->ranking())->toBeEmpty()
        ->and($page->instance()->canRecalculate())->toBeFalse();

    $page->set('employee', $employee->employee_id);
    expect($page->instance()->history())->toBeEmpty();

    $page->call('startRecalculation')->assertForbidden();
});

it('opens the basket size once the branch of the supervisor has a shift', function () {
    $record = ($this->recordWithoutShifts)($this->branchA);
    $employeeName = $record->employeeRecords()->firstOrFail()->employee_name;

    $this->branchA->salesShifts()->create(['shift_number' => 1, 'name' => 'Shift 1', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);

    ($this->openPage)()
        ->assertDontSee('Isi Shift Basket Size terlebih dahulu')
        ->assertSee($employeeName)
        ->assertSee('Hitung Ulang');
});

it('lists only the branches without shifts and needs every accessible active branch to be filled', function () {
    $this->supervisor->syncBranchAccess([$this->branchA->id, $this->branchB->id], $this->branchA->id);
    $this->branchB->salesShifts()->create(['shift_number' => 1, 'name' => 'Shift 1', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);

    ($this->openPage)()
        ->assertSee('Isi Shift Basket Size terlebih dahulu')
        ->assertSee('Kitchen Jateng')
        ->assertDontSee('Kitchen Jakarta');

    $this->branchA->salesShifts()->create(['shift_number' => 1, 'name' => 'Shift 1', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);

    ($this->openPage)()->assertDontSee('Isi Shift Basket Size terlebih dahulu');
});

it('does not require shifts for inactive branches or branches the supervisor cannot access', function () {
    $this->branchA->update(['is_active' => false]);

    ($this->openPage)()->assertDontSee('Isi Shift Basket Size terlebih dahulu');

    $this->branchA->update(['is_active' => true]);
    $this->branchA->salesShifts()->create(['shift_number' => 1, 'name' => 'Shift 1', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);

    ($this->openPage)()->assertDontSee('Isi Shift Basket Size terlebih dahulu');
});

it('only informs finance about branches without shifts and keeps the data open', function () {
    $record = ($this->recordWithoutShifts)($this->branchB);
    $employeeName = $record->employeeRecords()->firstOrFail()->employee_name;
    $finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->actingAs($finance);

    $page = ($this->openPage)()
        ->assertDontSee('Isi Shift Basket Size terlebih dahulu')
        ->assertSee('cabang aktif belum punya pengaturan Shift Basket Size')
        ->assertSee('Kitchen Jateng')
        ->assertSee('Kitchen Jakarta')
        ->assertSee($employeeName)
        ->assertSee('Hitung Ulang');

    expect($page->instance()->mustFillShiftsFirst())->toBeFalse()
        ->and($page->html())->not->toContain(BranchResource::getUrl('edit', ['record' => $this->branchB->id]));
});

it('links the branches without shifts to their edit page for users who can edit branches', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    ($this->openPage)()
        ->assertSee('cabang aktif belum punya pengaturan Shift Basket Size')
        ->assertSeeHtml(BranchResource::getUrl('edit', ['record' => $this->branchA->id]))
        ->assertSeeHtml(BranchResource::getUrl('edit', ['record' => $this->branchB->id]));
});
