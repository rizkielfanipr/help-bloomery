<?php

namespace App\Actions\Rnd\Bom;

use App\Enums\RndBomCatalogSyncStatus;
use App\Enums\RndBomChangeLogSource;
use App\Enums\RndBomChangeLogStatus;
use App\Models\RndBomCatalog;
use App\Models\RndBomChangeLog;
use App\Services\EsbBillOfMaterialService;
use App\Services\Rnd\Bom\BomChangeComparator;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Pulls every ESB BOM page, keeps only Assembly rows in the local `rnd_bom_catalogs` snapshot,
 * and records an `external_esb` change log when a BOM's detail drifted without a matching local
 * mutation (docs/rnd-bom-adjustment-prd.md §9, §16).
 *
 * "Active" / "Inactive" is derived from which `flagActive` browse pass returned the BOM, not
 * from a response field — the ESB detail payload does not reliably expose an active flag
 * (docs/rnd-bom-adjustment-prd.md §19 item 7 is an unproven contract), while the browse filter
 * parameter is already a proven, tested mechanism (EsbBillOfMaterialServiceTest).
 *
 * Every eligible BOM's full detail is fetched on every run (not only when browse metadata
 * suggests a change) because external-change detection requires a fresh snapshot to diff
 * against, and `component_count` can only be computed from the full `bomDetails` list — the
 * browse row alone is not proven to carry either (§19 items 1-2). This trades ESB call volume
 * for correctness; skip-if-unchanged is a candidate optimization once `editedDate` stability is
 * verified (§19 item 3), not a Phase 2 requirement.
 */
class SyncBomCatalogAction
{
    private const LOCK_KEY = 'rnd_bom_catalog_sync';

    private const PROGRESS_CACHE_KEY = 'rnd_bom_catalog_sync.progress';

    private const ASSEMBLY_BOM_TYPE_ID = 1;

    private const MAX_PAGES_PER_STATUS = 100;

    public function __construct(
        private readonly EsbBillOfMaterialService $bomService,
        private readonly BomChangeComparator $comparator,
    ) {}

    /** @return array{status: string, processed?: int, succeeded?: int, failed?: int} */
    public function execute(?int $triggeredBy = null): array
    {
        $lock = Cache::lock(self::LOCK_KEY, 600);

        if (! $lock->get()) {
            return ['status' => 'already_running'];
        }

        try {
            return $this->sync($triggeredBy);
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, mixed>|null */
    public static function progress(): ?array
    {
        return Cache::get(self::PROGRESS_CACHE_KEY);
    }

    /** @return array{status: string, processed: int, succeeded: int, failed: int} */
    private function sync(?int $triggeredBy): array
    {
        $startedAt = now();
        $processed = 0;
        $succeeded = 0;
        $failed = 0;
        $errors = [];
        $scanned = 0;
        $total = 0;

        $this->putProgress([
            'status' => 'running',
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'scanned' => 0,
            'total' => 0,
            'triggered_by' => $triggeredBy,
            'started_at' => $startedAt->toIso8601String(),
        ]);

        foreach ([true, false] as $isActive) {
            $page = 1;

            do {
                $result = $this->bomService->getBillOfMaterials([
                    'page' => $page,
                    'limit' => 100,
                    'flagActive' => $isActive ? 1 : 0,
                ]);

                if ($page === 1) {
                    $total += (int) $result['count'];
                }

                foreach ($result['data'] as $row) {
                    $scanned++;

                    if (! $this->isAssembly($row)) {
                        continue;
                    }

                    $processed++;

                    try {
                        $this->syncOne((int) $row['bomID'], $isActive);
                        $succeeded++;
                    } catch (Throwable $exception) {
                        $failed++;
                        $errors[] = "BOM {$row['bomID']}: {$exception->getMessage()}";
                    }
                }

                $this->putProgress([
                    'status' => 'running',
                    'processed' => $processed,
                    'succeeded' => $succeeded,
                    'failed' => $failed,
                    'scanned' => $scanned,
                    'total' => $total,
                    'triggered_by' => $triggeredBy,
                    'started_at' => $startedAt->toIso8601String(),
                ]);

                $page++;
                $hasNext = filled($result['next']) || (($result['page'] * $result['limit']) < $result['count']);
            } while ($hasNext && $page <= self::MAX_PAGES_PER_STATUS);
        }

        $this->putProgress([
            'status' => 'completed',
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'scanned' => $scanned,
            'total' => $total,
            'triggered_by' => $triggeredBy,
            'started_at' => $startedAt->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'errors' => array_slice($errors, 0, 20),
        ]);

        return ['status' => 'completed', 'processed' => $processed, 'succeeded' => $succeeded, 'failed' => $failed];
    }

    /** @param  array<string, mixed>  $row */
    private function isAssembly(array $row): bool
    {
        return (int) ($row['bomTypeID'] ?? 0) === self::ASSEMBLY_BOM_TYPE_ID
            || mb_strtolower(trim((string) ($row['bomTypeName'] ?? ''))) === 'assembly';
    }

    private function syncOne(int $bomId, bool $isActive): void
    {
        $detail = $this->bomService->getBillOfMaterial($bomId);
        $existing = RndBomCatalog::query()->where('esb_bom_id', $bomId)->first();

        if ($existing && is_array($existing->detail_snapshot)) {
            $before = $existing->detail_snapshot + ['is_active' => $existing->is_active];
            $after = $detail + ['is_active' => $isActive];
            $diff = $this->comparator->compare($before, $after);

            if ($diff['has_changes'] && ! $this->hasMatchingLocalMutation($bomId, $detail['editedDate'] ?? null)) {
                $this->logExternalChange($existing, $detail, $diff);
            }
        }

        RndBomCatalog::query()->updateOrCreate(
            ['esb_bom_id' => $bomId],
            [
                'bom_code' => $detail['bomCode'] ?? null,
                'bom_name' => $detail['bomName'] ?? null,
                'bom_type_id' => $detail['bomTypeID'] ?? null,
                'bom_type_name' => $detail['bomTypeName'] ?? null,
                'product_detail_id' => $detail['productDetailID'] ?? null,
                'product_code' => $detail['productCode'] ?? null,
                'product_name' => $detail['productName'] ?? null,
                'uom_name' => $detail['uomName'] ?? null,
                'component_count' => count($detail['bomDetails'] ?? []),
                'is_active' => $isActive,
                'detail_snapshot' => $detail,
                'esb_edited_at' => $detail['editedDate'] ?? null,
                'sync_status' => RndBomCatalogSyncStatus::Synced,
                'last_synced_at' => now(),
            ],
        );
    }

    private function hasMatchingLocalMutation(int $bomId, ?string $editedDateAfter): bool
    {
        if ($editedDateAfter === null) {
            return false;
        }

        return RndBomChangeLog::query()
            ->where('esb_bom_id', $bomId)
            ->whereIn('status', [RndBomChangeLogStatus::Success, RndBomChangeLogStatus::Pending])
            ->where('esb_edited_at_after', $editedDateAfter)
            ->exists();
    }

    /** @param  array<string, mixed>  $newDetail
     * @param  array<string, mixed>  $diff */
    private function logExternalChange(RndBomCatalog $catalog, array $newDetail, array $diff): void
    {
        RndBomChangeLog::query()->create([
            'esb_bom_id' => $catalog->esb_bom_id,
            'bom_code' => $newDetail['bomCode'] ?? $catalog->bom_code,
            'bom_name' => $newDetail['bomName'] ?? $catalog->bom_name,
            'product_code' => $newDetail['productCode'] ?? $catalog->product_code,
            'product_name' => $newDetail['productName'] ?? $catalog->product_name,
            'source' => RndBomChangeLogSource::ExternalEsb,
            'event' => 'external_change_detected',
            'status' => RndBomChangeLogStatus::Success,
            'reason' => null,
            'before_snapshot' => $catalog->detail_snapshot,
            'requested_snapshot' => null,
            'after_snapshot' => $newDetail,
            'changes' => $diff,
            'esb_edited_at_before' => $catalog->esb_edited_at,
            'esb_edited_at_after' => $newDetail['editedDate'] ?? null,
            'changed_by' => null,
        ]);
    }

    private function putProgress(array $progress): void
    {
        Cache::put(self::PROGRESS_CACHE_KEY, $progress, now()->addHours(2));
    }
}
