<?php

use App\Enums\RndBomCatalogSyncStatus;
use App\Enums\RndBomChangeLogSource;
use App\Enums\RndBomChangeLogStatus;
use App\Models\RndBomCatalog;
use App\Models\RndBomChangeLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('persists a BOM catalog snapshot row with casts', function () {
    $catalog = RndBomCatalog::factory()->create([
        'esb_bom_id' => 42,
        'detail_snapshot' => ['bomID' => 42, 'bomDetails' => []],
        'sync_status' => RndBomCatalogSyncStatus::NeedsReconciliation,
        'is_active' => false,
    ]);

    $fresh = $catalog->fresh();

    expect($fresh->esb_bom_id)->toBe(42)
        ->and($fresh->sync_status)->toBe(RndBomCatalogSyncStatus::NeedsReconciliation)
        ->and($fresh->is_active)->toBeFalse()
        ->and($fresh->detail_snapshot)->toBe(['bomID' => 42, 'bomDetails' => []])
        ->and($fresh->last_synced_at)->not->toBeNull();
});

it('rejects a duplicate esb_bom_id', function () {
    RndBomCatalog::factory()->create(['esb_bom_id' => 99]);

    expect(fn () => RndBomCatalog::factory()->create(['esb_bom_id' => 99]))
        ->toThrow(QueryException::class);
});

it('lets only users with the matching permission view and update the catalog', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo('view bill of materials');
    $editor = User::factory()->create(['is_active' => true]);
    $editor->givePermissionTo(['view bill of materials', 'edit bill of materials']);
    $outsider = User::factory()->create(['is_active' => true]);
    $catalog = RndBomCatalog::factory()->create();

    expect($viewer->can('viewAny', RndBomCatalog::class))->toBeTrue()
        ->and($viewer->can('view', $catalog))->toBeTrue()
        ->and($viewer->can('update', $catalog))->toBeFalse()
        ->and($editor->can('update', $catalog))->toBeTrue()
        ->and($outsider->can('viewAny', RndBomCatalog::class))->toBeFalse()
        ->and($outsider->can('view', $catalog))->toBeFalse();
});

it('persists a BOM change log row with snapshot casts and actor relations', function () {
    $actor = User::factory()->create();
    $log = RndBomChangeLog::factory()->create([
        'esb_bom_id' => 42,
        'source' => RndBomChangeLogSource::Project,
        'status' => RndBomChangeLogStatus::NeedsReconciliation,
        'before_snapshot' => ['bomID' => 42],
        'requested_snapshot' => ['bomID' => 42, 'bomDetails' => []],
        'after_snapshot' => null,
        'changes' => ['components_added' => []],
        'changed_by' => $actor->id,
    ]);

    $fresh = $log->fresh();

    expect($fresh->source)->toBe(RndBomChangeLogSource::Project)
        ->and($fresh->status)->toBe(RndBomChangeLogStatus::NeedsReconciliation)
        ->and($fresh->before_snapshot)->toBe(['bomID' => 42])
        ->and($fresh->changes)->toBe(['components_added' => []])
        ->and($fresh->changedBy->id)->toBe($actor->id);
});

it('does not delete change log rows when the referenced actor is deleted', function () {
    $actor = User::factory()->create();
    $log = RndBomChangeLog::factory()->create(['changed_by' => $actor->id]);

    $actor->delete();

    expect($log->fresh())->not->toBeNull()
        ->and($log->fresh()->changed_by)->toBeNull();
});

it('only allows reconciliation on a change log while its status is Needs Reconciliation', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['view bom adjustment history', 'reconcile bom adjustments']);

    foreach (RndBomChangeLogStatus::cases() as $status) {
        $log = RndBomChangeLog::factory()->create(['status' => $status]);

        expect($user->can('reconcile', $log))->toBe($status === RndBomChangeLogStatus::NeedsReconciliation)
            ->and($user->can('view', $log))->toBeTrue();
    }

    $outsider = User::factory()->create(['is_active' => true]);
    $needsReconciliation = RndBomChangeLog::factory()->create(['status' => RndBomChangeLogStatus::NeedsReconciliation]);

    expect($outsider->can('reconcile', $needsReconciliation))->toBeFalse()
        ->and($outsider->can('viewAny', RndBomChangeLog::class))->toBeFalse();
});

it('searches the local catalog by BOM or product code/name with default pagination of 20', function () {
    RndBomCatalog::factory()->create(['bom_code' => 'BOM-CRS', 'bom_name' => 'Croissant Assembly', 'product_code' => 'CRS', 'product_name' => 'Croissant']);
    RndBomCatalog::factory()->create(['bom_code' => 'BOM-DNT', 'bom_name' => 'Donut Assembly', 'product_code' => 'DNT', 'product_name' => 'Donut']);
    RndBomCatalog::factory()->count(25)->create();

    expect(RndBomCatalog::query()->search('croissant')->count())->toBe(1)
        ->and(RndBomCatalog::query()->search('DNT')->count())->toBe(1)
        ->and(RndBomCatalog::query()->search('')->count())->toBe(27);

    $paginated = RndBomCatalog::query()->paginate(20);
    expect($paginated->perPage())->toBe(20)
        ->and($paginated->total())->toBe(27)
        ->and($paginated->count())->toBe(20);
});
