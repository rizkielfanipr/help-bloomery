<?php

use App\Actions\Rnd\ShelfLife\CreateWipShelfLifeAction;
use App\Actions\Rnd\ShelfLife\UpdateWipShelfLifeAction;
use App\Enums\RndWipShelfLifeSource;
use App\Exceptions\Rnd\WipShelfLifeAlreadyExistsException;
use App\Models\RndProductEsbShelfLife;
use App\Models\User;
use App\Services\Rnd\ShelfLife\WipShelfLifeResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->bomEditor = User::factory()->create();
    $this->bomEditor->givePermissionTo(['view wip shelf life', 'manage wip shelf life']);
    $this->projectEditor = User::factory()->create();
    $this->projectEditor->givePermissionTo(['view rnd projects', 'edit rnd projects', 'manage wip shelf life']);

    $this->input = fn (array $overrides = []): array => array_merge([
        'esb_product_detail_id' => 9001,
        'product_code' => 'BW00123',
        'product_name' => 'Saus Keju',
        'shelf_life_value' => 3,
        'shelf_life_unit' => 'day',
        'storage_condition' => 'chiller',
        'notes' => 'Simpan 2–5°C',
    ], $overrides);

    $this->create = fn (array $overrides = [], RndWipShelfLifeSource $source = RndWipShelfLifeSource::ShelfLifeMenu, ?User $actor = null): RndProductEsbShelfLife => app(CreateWipShelfLifeAction::class)
        ->execute($source, ($this->input)($overrides), $actor ?? $this->bomEditor);
});

it('creates a new local master with actor audit and no ESB request', function () {
    Http::fake();

    $master = ($this->create)();

    expect($master->esb_product_detail_id)->toBe(9001)
        ->and($master->company_code)->toBe('BLSS')
        ->and($master->shelf_life_unit)->toBe('day')
        ->and($master->storage_condition)->toBe('chiller')
        ->and($master->notes)->toBe('Simpan 2–5°C')
        ->and($master->created_by)->toBe($this->bomEditor->id)
        ->and($master->updated_by)->toBe($this->bomEditor->id);

    Http::assertNothingSent();
});

it('never overwrites an existing master, active or inactive', function () {
    $existing = RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 9001, 'shelf_life_value' => 7, 'shelf_life_unit' => 'week']);

    try {
        ($this->create)(['shelf_life_value' => 1]);
        $this->fail('Expected an already-exists exception.');
    } catch (WipShelfLifeAlreadyExistsException $exception) {
        expect($exception->existing->is($existing))->toBeTrue();
    }

    $existing->update(['is_active' => false]);
    expect(fn () => ($this->create)())->toThrow(WipShelfLifeAlreadyExistsException::class);

    expect(RndProductEsbShelfLife::query()->count())->toBe(1)
        ->and($existing->fresh()->shelf_life_value)->toBe('7.00');
});

it('restores and refills a soft-deleted master instead of duplicating it', function () {
    $deleted = RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 9001, 'is_active' => false]);
    $deleted->delete();

    $master = ($this->create)(['shelf_life_value' => 5]);

    expect($master->id)->toBe($deleted->id)
        ->and($master->trashed())->toBeFalse()
        ->and($master->is_active)->toBeTrue()
        ->and($master->shelf_life_value)->toBe('5.00')
        ->and(RndProductEsbShelfLife::withTrashed()->count())->toBe(1);
});

it('keeps a single record when the same submission is repeated', function () {
    ($this->create)();

    expect(fn () => ($this->create)())->toThrow(WipShelfLifeAlreadyExistsException::class)
        ->and(RndProductEsbShelfLife::query()->count())->toBe(1);
});

it('rejects invalid values and enums', function (array $overrides, string $field) {
    try {
        ($this->create)($overrides);
        $this->fail('Expected validation to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }

    expect(RndProductEsbShelfLife::query()->count())->toBe(0);
})->with([
    'zero value' => [['shelf_life_value' => 0], 'shelf_life_value'],
    'negative value' => [['shelf_life_value' => -2], 'shelf_life_value'],
    'legacy unit' => [['shelf_life_unit' => 'hari'], 'shelf_life_unit'],
    'unknown storage' => [['storage_condition' => 'Freezer'], 'storage_condition'],
    'missing product detail' => [['esb_product_detail_id' => null], 'esb_product_detail_id'],
    'manipulated product detail' => [['esb_product_detail_id' => -5], 'esb_product_detail_id'],
    'foreign company' => [['company_code' => 'HACK'], 'company_code'],
]);

it('requires both project edit and Shelf Life manage permissions when creating from a Project', function () {
    $projectOnly = User::factory()->create();
    $projectOnly->givePermissionTo(['view rnd projects', 'edit rnd projects']);

    expect(fn () => ($this->create)([], RndWipShelfLifeSource::Project, $projectOnly))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->create)([], RndWipShelfLifeSource::Project, $this->bomEditor))->toThrow(AuthorizationException::class);

    expect(($this->create)([], RndWipShelfLifeSource::Project, $this->projectEditor)->exists)->toBeTrue();
});

it('updates only the allowlisted fields of an existing master through the update action', function () {
    $master = RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 9001, 'product_name' => 'Saus Keju']);

    $updated = app(UpdateWipShelfLifeAction::class)->execute($master, [
        'shelf_life_value' => 10, 'shelf_life_unit' => 'hour', 'storage_condition' => 'frozen', 'notes' => null,
        'esb_product_detail_id' => 1, 'product_name' => 'Diubah', 'company_code' => 'HACK',
    ], $this->bomEditor);

    expect($updated->id)->toBe($master->id)
        ->and($updated->shelf_life_value)->toBe('10.00')
        ->and($updated->shelf_life_unit)->toBe('hour')
        ->and($updated->storage_condition)->toBe('frozen')
        ->and($updated->esb_product_detail_id)->toBe(9001)
        ->and($updated->product_name)->toBe('Saus Keju')
        ->and($updated->company_code)->toBe('BLSS')
        ->and($updated->updated_by)->toBe($this->bomEditor->id)
        ->and(RndProductEsbShelfLife::query()->count())->toBe(1);
});

it('deactivates and reactivates a master, only for Shelf Life managers and never legacy Menu rows', function () {
    $master = RndProductEsbShelfLife::factory()->create();
    $action = app(UpdateWipShelfLifeAction::class);

    expect($action->setActive($master, false, $this->bomEditor)->is_active)->toBeFalse()
        ->and($action->setActive($master, true, $this->bomEditor)->is_active)->toBeTrue()
        ->and(fn () => $action->setActive($master, false, $this->projectEditor->revokePermissionTo('manage wip shelf life')))->toThrow(AuthorizationException::class)
        ->and(fn () => $action->setActive(RndProductEsbShelfLife::factory()->legacyMenu()->create(), false, $this->bomEditor))->toThrow(ValidationException::class);
});

it('resolves active masters with a constant number of deduplicated, company-isolated queries keyed by Product Detail ID', function () {
    $saus = RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 11]);
    $adonan = RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 12]);
    $inactive = RndProductEsbShelfLife::factory()->inactive()->create(['esb_product_detail_id' => 13]);
    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 14, 'company_code' => 'OTHR']);
    RndProductEsbShelfLife::factory()->legacyMenu()->create();

    DB::enableQueryLog();
    $active = app(WipShelfLifeResolver::class)->activeMasters([11, '12', 11, 12, 13, 14, null, 0, 99]);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($active->keys()->sort()->values()->all())->toBe([11, 12])
        ->and($active[11]->is($saus))->toBeTrue()
        ->and($active[12]->is($adonan))->toBeTrue()
        ->and($queries)->toBe(2)
        ->and(app(WipShelfLifeResolver::class)->masters([13])[13]->is($inactive))->toBeTrue()
        ->and(app(WipShelfLifeResolver::class)->activeMasters([14], 'OTHR'))->toHaveCount(1)
        ->and(app(WipShelfLifeResolver::class)->activeMasters([]))->toBeEmpty();
});
