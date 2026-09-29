<?php

namespace App\Actions\Rnd\Bom;

use App\Enums\RndBomChangeLogSource;
use App\Enums\RndBomChangeLogStatus;
use App\Exceptions\Rnd\BomConflictException;
use App\Exceptions\Rnd\BomInvariantException;
use App\Models\RndBomChangeLog;
use App\Models\User;
use App\Services\EsbBillOfMaterialService;
use App\Services\Rnd\Bom\BomChangeComparator;
use App\Services\Rnd\Bom\BomPayloadBuilder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Single centralized entry point for every BOM Assembly/Menu mutation — used by both the BOM
 * Adjustment editor and the Project inline editor so validation, concurrency check, mutation
 * safety, snapshot, and logging never diverge between callers (docs/rnd-bom-adjustment-prd.md
 * §2, §13).
 *
 * Authorization is the caller's responsibility (Page-level `edit bill of materials` /
 * `edit rnd projects` checks) — this Action assumes it has already been granted, matching the
 * layering in docs/code-remediation-prd.md §6 ("Action: satu use case, transaction boundary").
 */
class UpdateEsbBillOfMaterialAction
{
    public function __construct(
        private readonly EsbBillOfMaterialService $bomService,
        private readonly BomPayloadBuilder $payloadBuilder,
        private readonly BomChangeComparator $comparator,
    ) {}

    /**
     * @param  array<string, mixed>  $draft  edited productDetailID/productName/productCode/uomName + bomDetails
     *
     * @throws BomConflictException when `editedDate` changed in ESB since the form was loaded
     * @throws BomInvariantException when the edited component list violates a recipe invariant
     */
    public function execute(
        int $bomId,
        array $draft,
        ?string $loadedEditedDate,
        ?string $reason,
        RndBomChangeLogSource $source,
        ?User $actor,
    ): RndBomChangeLog {
        $latest = $this->bomService->getBillOfMaterial($bomId);
        $latestEditedDate = $latest['editedDate'] ?? null;

        if ($loadedEditedDate && $latestEditedDate && $loadedEditedDate !== $latestEditedDate) {
            throw new BomConflictException('BOM telah diperbarui user lain di ESB. Muat ulang komponen sebelum menyimpan.');
        }

        $this->validateInvariants($latest, $draft);

        $payload = $this->payloadBuilder->build($latest, $draft);

        $changeLog = RndBomChangeLog::query()->create([
            'esb_bom_id' => $bomId,
            'bom_code' => $latest['bomCode'] ?? null,
            'bom_name' => $latest['bomName'] ?? null,
            'product_code' => $latest['productCode'] ?? null,
            'product_name' => $latest['productName'] ?? null,
            'source' => $source,
            'event' => 'component_updated',
            'status' => RndBomChangeLogStatus::Pending,
            'reason' => $reason,
            'before_snapshot' => $latest,
            'requested_snapshot' => $payload,
            'esb_edited_at_before' => $latestEditedDate,
            'changed_by' => $actor?->id,
        ]);

        try {
            $this->bomService->updateBillOfMaterial($bomId, $payload);
        } catch (ConnectionException $exception) {
            $changeLog->update([
                'status' => RndBomChangeLogStatus::NeedsReconciliation,
                'error_message' => $exception->getMessage(),
            ]);

            return $changeLog->fresh();
        } catch (Throwable $exception) {
            $changeLog->update([
                'status' => RndBomChangeLogStatus::Failed,
                'error_code' => (string) $exception->getCode() ?: null,
                'error_message' => $exception->getMessage(),
            ]);

            return $changeLog->fresh();
        }

        try {
            $after = $this->bomService->getBillOfMaterial($bomId);
        } catch (Throwable $exception) {
            // The mutation was accepted by ESB (no exception above), but its confirmed state
            // could not be verified — treat the outcome as unknown rather than guessing.
            $changeLog->update([
                'status' => RndBomChangeLogStatus::NeedsReconciliation,
                'error_message' => $exception->getMessage(),
            ]);

            return $changeLog->fresh();
        }

        $diff = $this->comparator->compare($latest, $after);

        $changeLog->update([
            'status' => RndBomChangeLogStatus::Success,
            'after_snapshot' => $after,
            'changes' => $diff,
            'esb_edited_at_after' => $after['editedDate'] ?? null,
        ]);

        return $changeLog->fresh();
    }

    /**
     * @param  array<string, mixed>  $latest
     * @param  array<string, mixed>  $draft
     */
    private function validateInvariants(array $latest, array $draft): void
    {
        $isMenu = $this->payloadBuilder->isMenu($latest);
        $bomDetails = $draft['bomDetails'] ?? [];

        if (count($bomDetails) < 1) {
            throw new BomInvariantException('bomDetails', 'BOM wajib memiliki minimal satu komponen.');
        }

        $productDetailIds = collect($bomDetails)->pluck('productDetailID')->map(fn ($id): int => (int) $id);

        if ($productDetailIds->duplicates()->isNotEmpty()) {
            throw new BomInvariantException('bomDetails', 'Komponen tidak boleh memiliki Product Detail yang sama.');
        }

        if (! $isMenu && $productDetailIds->contains((int) ($draft['productDetailID'] ?? 0))) {
            throw new BomInvariantException(
                'productDetailID',
                'Product hasil tidak boleh digunakan sebagai komponen pada BOM yang sama.',
            );
        }

        /** @var Collection<int, array<string, mixed>> $components */
        $components = collect($bomDetails);

        if ($components->contains(fn (array $component): bool => (float) ($component['qty'] ?? 0) <= 0)) {
            throw new BomInvariantException('bomDetails', 'Quantity setiap komponen harus lebih dari nol.');
        }
    }
}
