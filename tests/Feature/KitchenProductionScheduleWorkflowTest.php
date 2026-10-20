<?php

use App\Actions\Kitchen\CancelProductionScheduleAction;
use App\Actions\Kitchen\SaveProductionScheduleAction;
use App\Enums\KitchenProductionScheduleStatus;
use App\Models\Branch;
use App\Models\KitchenProductionSchedule;
use App\Models\RndBomCatalog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

const KITCHEN_PERMISSIONS = [
    'view kitchen production schedules',
    'create kitchen production schedules',
    'update kitchen production schedules',
    'cancel kitchen production schedules',
];

function productionPlanner(Branch $branch, array $permissions = KITCHEN_PERMISSIONS): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo($permissions);
    $user->syncBranchAccess([$branch->id], $branch->id);

    return $user->refresh();
}

function productionAssembly(array $overrides = []): RndBomCatalog
{
    return RndBomCatalog::factory()->create($overrides);
}

function scheduleInput(Branch $branch, array $catalogs, string $date = '2026-10-20', array $quantities = []): array
{
    return [
        'branch_id' => $branch->id,
        'production_date' => $date,
        'notes' => 'Produksi pagi',
        'items' => array_map(fn (RndBomCatalog $catalog, int $index): array => [
            'rnd_bom_catalog_id' => $catalog->id,
            'planned_quantity' => $quantities[$index] ?? '10',
            'notes' => null,
        ], $catalogs, array_keys($catalogs)),
    ];
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->kitchen()->create(['is_active' => true]);
    $this->planner = productionPlanner($this->branch);
    $this->save = app(SaveProductionScheduleAction::class);
    $this->cancel = app(CancelProductionScheduleAction::class);
});

it('saves a scheduled plan immediately with multiple Assembly BOM snapshots', function () {
    [$a, $b] = [productionAssembly(), productionAssembly(['uom_name' => 'KG'])];

    $schedule = $this->save->execute($this->planner, scheduleInput($this->branch, [$a, $b], quantities: ['2,5', '0012.3400']));
    $items = $schedule->items()->get();

    expect($schedule->status)->toBe(KitchenProductionScheduleStatus::Scheduled)
        ->and($items->pluck('sequence')->all())->toBe([1, 2])
        ->and($items->pluck('planned_quantity')->all())->toBe(['2.5000', '12.3400'])
        ->and($items->pluck('uom_name')->all())->toBe(['PCS', 'KG'])
        ->and($items->first()->bom_code)->toBe($a->bom_code);
});

it('requires at least one BOM and rejects invalid quantities', function (array|Closure $items, string $message) {
    $items = $items instanceof Closure ? $items() : $items;

    expect(fn () => $this->save->execute($this->planner, [...scheduleInput($this->branch, []), 'items' => $items]))
        ->toThrow(ValidationException::class, $message)
        ->and(KitchenProductionSchedule::count())->toBe(0);
})->with([
    'empty' => [[], 'Tambahkan minimal satu BOM Assembly.'],
    'zero' => [fn () => [['rnd_bom_catalog_id' => productionAssembly()->id, 'planned_quantity' => '0']], 'Jumlah produksi harus lebih dari nol.'],
]);

it('rejects duplicate, forged and non-Assembly BOM IDs', function () {
    $bom = productionAssembly();

    expect(fn () => $this->save->execute($this->planner, [...scheduleInput($this->branch, []), 'items' => [
        ['rnd_bom_catalog_id' => $bom->id, 'planned_quantity' => '1'],
        ['rnd_bom_catalog_id' => $bom->id, 'planned_quantity' => '2'],
    ]]))->toThrow(ValidationException::class);

    expect(fn () => $this->save->execute($this->planner, [...scheduleInput($this->branch, []), 'items' => [
        ['rnd_bom_catalog_id' => 999999, 'planned_quantity' => '1'],
    ]]))->toThrow(ValidationException::class, 'BOM tidak ditemukan di katalog.');

    $menu = productionAssembly(['bom_type_id' => 3, 'bom_type_name' => 'Assembly']);
    expect(fn () => $this->save->execute($this->planner, scheduleInput($this->branch, [$menu])))
        ->toThrow(ValidationException::class, 'Bukan BOM Assembly.');
});

it('updates and reorders a scheduled plan atomically', function () {
    [$a, $b, $c] = [productionAssembly(), productionAssembly(), productionAssembly()];
    $schedule = $this->save->execute($this->planner, scheduleInput($this->branch, [$a, $b]));
    $updatedAt = $schedule->updated_at->toIso8601String();

    $updated = $this->save->execute(
        $this->planner,
        scheduleInput($this->branch, [$c, $a], date: '2026-10-21', quantities: ['3', '4']),
        $schedule,
        $updatedAt,
    );

    expect($updated->production_date->toDateString())->toBe('2026-10-21')
        ->and($updated->items()->orderBy('sequence')->pluck('rnd_bom_catalog_id')->all())->toBe([$c->id, $a->id])
        ->and($updated->items()->pluck('planned_quantity')->all())->toBe(['3.0000', '4.0000']);
});

it('prevents duplicate slots and stale edits', function () {
    $bom = productionAssembly();
    $schedule = $this->save->execute($this->planner, scheduleInput($this->branch, [$bom]));

    expect(fn () => $this->save->execute($this->planner, scheduleInput($this->branch, [productionAssembly()])))
        ->toThrow(ValidationException::class, 'Jadwal untuk Branch dan tanggal ini sudah ada.');

    $stale = $schedule->updated_at->toIso8601String();
    $this->travel(1)->seconds();
    $schedule->update(['notes' => 'Diubah pengguna lain']);

    expect(fn () => $this->save->execute($this->planner, scheduleInput($this->branch, [$bom], date: '2026-10-22'), $schedule, $stale))
        ->toThrow(ValidationException::class, 'Jadwal sudah diubah oleh pengguna lain.');
});

it('requires active accessible Branches and action permissions', function () {
    $other = Branch::factory()->kitchen()->create(['is_active' => true]);

    expect(fn () => $this->save->execute($this->planner, scheduleInput($other, [productionAssembly()])))
        ->toThrow(AuthorizationException::class);

    $inactive = Branch::factory()->kitchen()->create(['is_active' => false]);
    $inactivePlanner = productionPlanner($inactive);
    expect(fn () => $this->save->execute($inactivePlanner, scheduleInput($inactive, [productionAssembly()])))
        ->toThrow(ValidationException::class, 'Branch ini tidak aktif.');

    $viewer = productionPlanner($this->branch, ['view kitchen production schedules']);
    expect(fn () => $this->save->execute($viewer, scheduleInput($this->branch, [productionAssembly()])))
        ->toThrow(AuthorizationException::class);

    $nonKitchen = Branch::factory()->create(['is_active' => true]);
    $nonKitchenPlanner = productionPlanner($nonKitchen);
    expect(fn () => $this->save->execute($nonKitchenPlanner, scheduleInput($nonKitchen, [productionAssembly()])))
        ->toThrow(ValidationException::class, 'Branch harus terhubung ke Brand Kitchen.');

    $nonKitchenPlanner->forceFill(['access_all_branches' => true])->save();
    expect($this->save->execute($nonKitchenPlanner->refresh(), scheduleInput($nonKitchen, [productionAssembly()])))
        ->toBeInstanceOf(KitchenProductionSchedule::class);
});

it('cancels idempotently without removing items and makes the plan read-only', function () {
    $bom = productionAssembly();
    $schedule = $this->save->execute($this->planner, scheduleInput($this->branch, [$bom]));

    $this->cancel->execute($this->planner, $schedule, 'Oven rusak.');
    $this->cancel->execute($this->planner, $schedule, 'Alasan kedua.');

    expect($schedule->refresh()->status)->toBe(KitchenProductionScheduleStatus::Cancelled)
        ->and($schedule->cancellation_reason)->toBe('Oven rusak.')
        ->and($schedule->items()->count())->toBe(1)
        ->and(fn () => $this->save->execute($this->planner, scheduleInput($this->branch, [$bom], date: '2026-10-22'), $schedule))
        ->toThrow(AuthorizationException::class);
});

it('requires a cancellation reason', function () {
    $schedule = $this->save->execute($this->planner, scheduleInput($this->branch, [productionAssembly()]));

    expect(fn () => $this->cancel->execute($this->planner, $schedule, ' '))
        ->toThrow(ValidationException::class, 'Alasan pembatalan wajib diisi.');
});
