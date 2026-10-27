<?php

namespace App\Services\Rnd\Material;

use App\Enums\MaterialEsbSyncStatus;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Writes the outcome of an ESB link/create/update (§13.3). `synced` is only stored with a full
 * verified Product Detail mapping; everything uncertain becomes `needs_reconciliation`; only a
 * proven non-applied mutation becomes `failed` (safe to retry with the same approval).
 */
class MaterialEsbSyncFinalizer
{
    public function __construct(
        private readonly MaterialAuditLogger $audit,
        private readonly MaterialWorkflowNotifier $notifier,
        private readonly MaterialPostSyncRouter $router,
    ) {}

    /**
     * @param  int  $productId  the Product ID that was requested or created; ESB's read-back body does not carry it
     * @param  array<string, mixed>  $remoteProduct
     * @param  array<int, int>  $mapping  local unit ID => Product Detail ID
     */
    public function markSynced(RndProductEsbMaterial $material, int $productId, array $remoteProduct, array $mapping, ?User $actor, ?RndMaterialReview $review, string $how): RndProductEsbMaterial
    {
        $material = DB::transaction(function () use ($material, $productId, $remoteProduct, $mapping, $actor, $review, $how): RndProductEsbMaterial {
            $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);
            foreach ($mapping as $unitId => $detailId) {
                $material->units()->whereKey($unitId)->update(['esb_product_detail_id' => $detailId]);
            }

            $baseDetailId = $mapping[$material->units()->where('is_base', true)->value('id')] ?? null;
            $previous = $material->effectiveEsbSyncStatus();
            $material->forceFill([
                'esb_product_id' => $productId,
                'esb_product_code' => (string) ($remoteProduct['productCode'] ?? $material->product_code),
                'esb_product_detail_id' => $baseDetailId ?? $material->esb_product_detail_id,
                'esb_is_temp' => (bool) ($remoteProduct['isTemp'] ?? $material->esb_is_temp),
                'esb_sync_status' => MaterialEsbSyncStatus::Synced,
                'status' => MaterialEsbSyncStatus::Synced->legacyStatus(),
                'esb_verified_at' => now(),
                'esb_sync_claimed_at' => null,
                'synced_at' => now(),
                'sync_error' => null,
                'last_response' => ['productID' => $productId, 'productDetailIDs' => array_values($mapping), 'verified' => true],
            ])->save();
            $review?->forceFill(['consumed_at' => now()])->save();

            $this->audit->event($material, 'esb_synced', $actor, from: $previous->value, to: MaterialEsbSyncStatus::Synced->value, metadata: ['how' => $how, 'esb_product_id' => $productId, 'product_detail_ids' => $mapping], review: $review);
            $this->notifier->notify($material, 'esb_synced', 'Master Bahan tersinkron ke ESB: '.$material->product_code, 'Product ID dan seluruh Product Detail ID terverifikasi; bahan dapat dipakai pada resep/BOM.', includeCreator: true);

            return $material;
        });

        $this->router->route($material, $actor);

        return $material->refresh();
    }

    public function markNeedsReconciliation(RndProductEsbMaterial $material, string $reason, ?User $actor, ?int $productId = null): void
    {
        $this->finish($material, MaterialEsbSyncStatus::NeedsReconciliation, $reason, $actor, $productId);
        $this->notifier->notify($material, 'esb_failed', 'Master Bahan perlu rekonsiliasi ESB: '.$material->product_code, $reason, includeCreator: true);
    }

    public function markFailed(RndProductEsbMaterial $material, string $reason, ?User $actor): void
    {
        $this->finish($material, MaterialEsbSyncStatus::Failed, $reason, $actor);
        $this->notifier->notify($material, 'esb_failed', 'Push Master Bahan gagal: '.$material->product_code, $reason, includeCreator: true);
    }

    private function finish(RndProductEsbMaterial $material, MaterialEsbSyncStatus $status, string $reason, ?User $actor, ?int $productId = null): void
    {
        DB::transaction(function () use ($material, $status, $reason, $actor, $productId): void {
            $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);
            $previous = $material->effectiveEsbSyncStatus();
            $material->forceFill(array_filter([
                'esb_sync_status' => $status,
                'status' => $status->legacyStatus(),
                'esb_sync_claimed_at' => null,
                'sync_error' => mb_substr($reason, 0, 2000),
                'esb_product_id' => $productId ?: null,
            ], fn ($value): bool => $value !== null))->save();
            $this->audit->event($material, $status === MaterialEsbSyncStatus::Failed ? 'esb_failed' : 'esb_needs_reconciliation', $actor, $reason, $previous->value, $status->value, ['esb_product_id' => $productId]);
        });
    }
}
