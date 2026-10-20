<?php

use App\Enums\KitchenProductionScheduleStatus;
use App\Models\Branch;
use App\Models\KitchenProductionSchedule;
use App\Models\KitchenProductionScheduleItem;
use App\Models\RndBomCatalog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('has the schedule and item schema with the required constraints', function () {
    expect(Schema::hasColumns('kitchen_production_schedules', [
        'branch_id', 'production_date', 'status', 'notes', 'created_by', 'updated_by',
        'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('kitchen_production_schedule_items', [
            'rnd_bom_catalog_id', 'esb_bom_id', 'bom_code', 'bom_name', 'bom_type_id',
            'product_detail_id', 'product_code', 'product_name', 'uom_name',
            'planned_quantity', 'sequence', 'notes',
        ]))->toBeTrue();

    $indexes = collect(Schema::getIndexes('kitchen_production_schedules'))->keyBy('name');

    expect($indexes['kitchen_schedules_branch_date_unique']['unique'])->toBeTrue()
        ->and($indexes['kitchen_schedules_branch_status_date_index']['columns'])->toBe(['branch_id', 'status', 'production_date']);
});

it('defaults schedules to Scheduled and casts dates and status', function () {
    $branch = Branch::factory()->kitchen()->create(['is_active' => true]);
    $id = DB::table('kitchen_production_schedules')->insertGetId([
        'branch_id' => $branch->id,
        'production_date' => '2026-11-04',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $schedule = KitchenProductionSchedule::findOrFail($id);

    expect((new KitchenProductionSchedule)->status)->toBe(KitchenProductionScheduleStatus::Scheduled)
        ->and($schedule->status)->toBe(KitchenProductionScheduleStatus::Scheduled)
        ->and($schedule->production_date)->toBeInstanceOf(CarbonImmutable::class);
});

it('allows one schedule per Branch and date and unique BOM sequences', function () {
    $schedule = KitchenProductionSchedule::factory()->create(['production_date' => '2026-11-05']);

    expect(fn () => KitchenProductionSchedule::factory()->forBranch($schedule->branch)->onDate('2026-11-05')->create())
        ->toThrow(UniqueConstraintViolationException::class);

    KitchenProductionScheduleItem::factory()->for($schedule, 'schedule')->create(['esb_bom_id' => 501, 'sequence' => 1]);

    expect(fn () => KitchenProductionScheduleItem::factory()->for($schedule, 'schedule')->create(['esb_bom_id' => 501, 'sequence' => 2]))
        ->toThrow(UniqueConstraintViolationException::class)
        ->and(fn () => KitchenProductionScheduleItem::factory()->for($schedule, 'schedule')->create(['esb_bom_id' => 502, 'sequence' => 1]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('stores planned quantity exactly as four decimal places', function () {
    $item = KitchenProductionScheduleItem::factory()->create(['planned_quantity' => '9876543.2109']);

    expect($item->refresh()->planned_quantity)->toBe('9876543.2109')
        ->and(KitchenProductionScheduleItem::factory()->create(['planned_quantity' => '2.5'])->refresh()->planned_quantity)->toBe('2.5000');
});

it('keeps lightweight snapshots when actors or catalog rows are removed', function () {
    $creator = User::factory()->create();
    $catalog = RndBomCatalog::factory()->create();
    $schedule = KitchenProductionSchedule::factory()->create(['created_by' => $creator->id]);
    $item = KitchenProductionScheduleItem::factory()->for($schedule, 'schedule')->assemblyItem($catalog)->create();

    expect(fn () => $schedule->branch->delete())->toThrow(QueryException::class);

    $creator->delete();
    $catalog->delete();

    expect($schedule->refresh()->created_by)->toBeNull()
        ->and($item->refresh()->rnd_bom_catalog_id)->toBeNull()
        ->and($item->bom_code)->toBe($catalog->bom_code);
});

it('allows only Scheduled to transition to Cancelled', function () {
    expect(KitchenProductionScheduleStatus::Scheduled->canTransitionTo(KitchenProductionScheduleStatus::Cancelled))->toBeTrue()
        ->and(KitchenProductionScheduleStatus::Cancelled->allowedTransitions())->toBe([])
        ->and(KitchenProductionScheduleStatus::Scheduled->getLabel())->toBe('Scheduled')
        ->and(KitchenProductionScheduleStatus::Cancelled->getLabel())->toBe('Cancelled');
});
