<?php

use App\Enums\RndShelfLifeUnit;
use App\Models\RndBomCatalog;
use App\Models\RndProductEsbShelfLife;
use App\Models\User;
use App\Services\Rnd\ShelfLife\WipShelfLifeIdentityAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

it('creates a WIP master through the factory with the expected casts', function () {
    $creator = User::factory()->create();
    $master = RndProductEsbShelfLife::factory()->create([
        'esb_product_detail_id' => 4321, 'shelf_life_value' => 3, 'shelf_life_unit' => 'day',
        'storage_condition' => 'chiller', 'created_by' => $creator->id, 'updated_by' => $creator->id,
    ]);

    expect($master->esb_product_detail_id)->toBe(4321)
        ->and($master->shelf_life_value)->toBe('3.00')
        ->and($master->is_active)->toBeTrue()
        ->and($master->company_code)->toBe('BLSS')
        ->and($master->shelfLifeUnit())->toBe(RndShelfLifeUnit::Day)
        ->and($master->shelfLifeLabel())->toBe('3 Hari')
        ->and($master->storageConditionLabel())->toBe('Chiller')
        ->and($master->creator->is($creator))->toBeTrue()
        ->and($master->updater->is($creator))->toBeTrue();
});

it('enforces one master per company and Product Detail ID, including soft-deleted rows', function () {
    $master = RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 777]);

    expect(fn () => RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 777]))
        ->toThrow(UniqueConstraintViolationException::class);

    $master->delete();

    expect(fn () => RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 777]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows the same Product Detail ID in another company and multiple legacy Menu rows', function () {
    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 777]);
    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 777, 'company_code' => 'OTHR']);
    RndProductEsbShelfLife::factory()->legacyMenu()->count(2)->create();

    expect(RndProductEsbShelfLife::query()->count())->toBe(4);
});

it('keeps legacy Menu-only rows out of WIP lookups', function () {
    $wip = RndProductEsbShelfLife::factory()->create();
    RndProductEsbShelfLife::factory()->legacyMenu()->create();
    RndProductEsbShelfLife::factory()->inactive()->create();

    expect(RndProductEsbShelfLife::query()->wipMaster()->count())->toBe(2)
        ->and(RndProductEsbShelfLife::query()->activeWipMaster()->pluck('id')->all())->toBe([$wip->id]);
});

it('maps only registered legacy units and reports unknown ones as null', function (?string $stored, ?RndShelfLifeUnit $expected) {
    expect(RndShelfLifeUnit::fromLegacy($stored))->toBe($expected);
})->with([
    'current key' => ['week', RndShelfLifeUnit::Week],
    'jam' => ['jam', RndShelfLifeUnit::Hour],
    'hari' => ['Hari', RndShelfLifeUnit::Day],
    'minggu' => ['minggu', RndShelfLifeUnit::Week],
    'bulan' => [' bulan ', RndShelfLifeUnit::Month],
    'tahun' => ['tahun', RndShelfLifeUnit::Year],
    'unknown' => ['dekade', null],
    'empty' => [null, null],
]);

it('rolls the unique identity migration back and forth', function () {
    $migration = require database_path('migrations/2026_10_06_142950_add_wip_identity_unique_to_rnd_esb_product_shelf_lives_table.php');
    $uniqueIndexExists = fn (): bool => collect(Schema::getIndexes('rnd_esb_product_shelf_lives'))
        ->contains(fn (array $index): bool => $index['name'] === 'rnd_shelf_life_company_product_detail_unique' && $index['unique']);

    expect($uniqueIndexExists())->toBeTrue();

    $migration->down();
    expect($uniqueIndexExists())->toBeFalse();

    RndProductEsbShelfLife::factory()->count(2)->create(['esb_product_detail_id' => 555]);
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'rnd:audit-wip-shelf-life');

    RndProductEsbShelfLife::query()->where('esb_product_detail_id', 555)->forceDelete();
    $migration->up();
    expect($uniqueIndexExists())->toBeTrue();
});

it('reports identity conflicts and legacy data in the dry-run audit without changing anything', function () {
    RndBomCatalog::factory()->create(['product_detail_id' => 1001]);
    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 1001]);
    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 2002]);
    $menuOnly = RndProductEsbShelfLife::factory()->legacyMenu()->create(['shelf_life_unit' => 'hari']);
    $mixed = RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 3003, 'esb_menu_id' => 50, 'shelf_life_unit' => 'dekade']);
    $before = RndProductEsbShelfLife::withTrashed()->get()->toArray();

    $this->artisan('rnd:audit-wip-shelf-life')
        ->expectsOutputToContain('Tidak ada konflik identity')
        ->assertSuccessful();

    $report = app(WipShelfLifeIdentityAudit::class)->report();

    expect($report['menu_only'])->toBe([$menuOnly->id])
        ->and($report['menu_and_product_detail'])->toBe([$mixed->id])
        ->and($report['unknown_units'])->toBe([['id' => $mixed->id, 'value' => 'dekade']])
        ->and(collect($report['legacy_units'])->pluck('maps_to')->all())->toBe(['day'])
        ->and(collect($report['unproven_wip_product_detail_ids'])->pluck('esb_product_detail_id')->sort()->values()->all())->toBe([2002, 3003])
        ->and(RndProductEsbShelfLife::withTrashed()->get()->toArray())->toBe($before);
});

it('records activity only for business fields', function () {
    $master = RndProductEsbShelfLife::factory()->create(['shelf_life_value' => 2]);

    $master->update(['shelf_life_value' => 4, 'updated_by' => User::factory()->create()->id]);

    $activity = Activity::query()->where('subject_id', $master->id)->latest('id')->first();

    expect($activity->attribute_changes['attributes'] ?? $activity->properties['attributes'] ?? [])->toHaveKey('shelf_life_value')
        ->not->toHaveKey('updated_by');
});
