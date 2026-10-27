<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialEsbSyncStatus;
use App\Enums\MonitoringTransactionStatus;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\EsbMasterProductService;
use App\Services\Monitoring\EsbTransactionRecorder;
use App\Services\Monitoring\EsbTransactionScope;
use App\Services\Rnd\Material\MaterialDetailMapper;
use App\Services\Rnd\Material\MaterialEsbGate;
use App\Services\Rnd\Material\MaterialEsbSyncFinalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The only path that creates or updates a Master Bahan product in ESB (§13, §21). Every caller —
 * IT approval, safe retry of a deterministic failure — goes through the IT approval gate and an
 * atomic local claim, so double clicks or a second tab never send a second mutation.
 *
 * Create: an exact existing product code is never re-created; it goes to reconciliation so IT can
 * link it. Update of an already synced product reuses the canonical Product Detail replacement
 * flow. The result is always read back and every active unit must map to one Product Detail
 * before the material is `synced`. An uncertain outcome after the mutation was sent becomes
 * `needs_reconciliation`, never an automatic retry.
 */
class SyncMaterialToEsbAction
{
    public function __construct(
        private readonly MaterialEsbGate $gate,
        private readonly EsbMasterProductService $esb,
        private readonly MaterialDetailMapper $mapper,
        private readonly MaterialEsbSyncFinalizer $finalizer,
        private readonly EsbTransactionRecorder $recorder,
    ) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor): RndProductEsbMaterial
    {
        if (! Gate::forUser($actor)->any(['approveEsb', 'reconcileEsb'], $material)) {
            throw new AuthorizationException('Anda tidak berhak mengirim Master Bahan ke ESB.');
        }

        $review = $this->gate->approvedReview($material);
        $claimed = RndProductEsbMaterial::query()->whereKey($material->id)
            ->whereIn('esb_sync_status', [MaterialEsbSyncStatus::Queued->value, MaterialEsbSyncStatus::Failed->value])
            ->update([
                'esb_sync_status' => MaterialEsbSyncStatus::Syncing->value,
                'status' => MaterialEsbSyncStatus::Syncing->legacyStatus(),
                'esb_sync_claimed_at' => now(),
            ]);
        if ($claimed !== 1) {
            throw ValidationException::withMessages(['esb' => 'Proses ESB untuk bahan ini sedang berjalan atau sudah selesai. Permintaan ganda dihentikan.']);
        }

        $material->refresh();
        $isUpdate = (int) $material->esb_product_id > 0;
        $payload = $material->toEsbPayload();
        $mutationStarted = false;
        $productId = $material->esb_product_id;
        $attemptStatus = null;

        try {
            $remote = $this->recorder->transaction('rnd_product_release', $isUpdate ? 'approved_esb_update' : 'approved_esb_create', $isUpdate ? 'update_product' : 'create_product',
                function (EsbTransactionScope $scope) use ($material, $payload, $isUpdate, &$mutationStarted, &$productId, &$attemptStatus): ?array {
                    try {
                        if ($isUpdate) {
                            $remoteBefore = $this->esb->findProductById((int) $material->esb_product_id) ?? [];
                            $this->backfillMissingDetailIds($material, $remoteBefore);
                            $payload = $material->refresh()->toEsbPayload();
                            $mutationStarted = true;
                            $this->esb->updateProductWithUnitReplacements((int) $material->esb_product_id, $payload, $remoteBefore);
                        } else {
                            $existing = collect($this->esb->getProducts(['limit' => 20, 'productCode' => $material->product_code])['data'])
                                ->filter(fn (array $product): bool => mb_strtoupper(trim((string) ($product['productCode'] ?? ''))) === mb_strtoupper($material->product_code));
                            if ($existing->isNotEmpty()) {
                                $productId = $existing->count() === 1 ? (int) $existing->first()['productID'] : null;

                                return null;
                            }

                            $mutationStarted = true;
                            $productId = $this->esb->createProduct($payload)['productID'];
                            $scope->reference($productId);
                        }

                        $scope->timeline('read_back', 'requested');

                        return $this->esb->findProductById((int) $productId);
                    } catch (Throwable $exception) {
                        $attemptStatus = collect($scope->attempts)->last()?->status;

                        throw $exception;
                    }
                }, ['local_model' => $material, 'idempotency_key' => "material:{$material->id}:v{$material->submission_version}", 'actor' => $actor]);
        } catch (Throwable $exception) {
            report($exception);
            $message = 'ESB: '.mb_strimwidth($exception->getMessage(), 0, 400, '…');

            if (! $mutationStarted || $attemptStatus === MonitoringTransactionStatus::Failed) {
                $this->finalizer->markFailed($material, $message, $actor);
            } else {
                $this->finalizer->markNeedsReconciliation($material, 'Hasil mutation ESB tidak pasti; periksa ESB sebelum tindakan lanjut. '.$message, $actor, $productId ? (int) $productId : null);
            }

            return $material->refresh();
        }

        if (! $mutationStarted && ! $isUpdate) {
            $this->finalizer->markNeedsReconciliation($material, 'Kode produk sudah ada di ESB. Produk tidak dibuat ulang; verifikasi lalu tautkan produk existing.', $actor, $productId ? (int) $productId : null);

            return $material->refresh();
        }

        if (! is_array($remote)) {
            $this->finalizer->markNeedsReconciliation($material, 'Produk tidak dapat dibaca kembali dari ESB setelah mutation.', $actor, $productId ? (int) $productId : null);

            return $material->refresh();
        }

        try {
            $result = $this->mapper->map($material, $remote);
            if (! $result['ok']) {
                $this->finalizer->markNeedsReconciliation($material, 'Mapping Product Detail belum lengkap: '.implode(' ', $result['errors']), $actor, (int) $productId);

                return $material->refresh();
            }

            return $this->finalizer->markSynced($material, (int) $productId, $remote, $result['mapping'], $actor, $review, $isUpdate ? 'updated' : 'created');
        } catch (Throwable $exception) {
            // The ESB mutation already succeeded; a local failure while recording it must never leave
            // the claim stuck in `syncing`. IT verifies the known Product ID instead of re-sending.
            report($exception);
            $this->finalizer->markNeedsReconciliation($material, 'Produk sudah tercatat di ESB tetapi hasilnya gagal disimpan lokal; verifikasi Product ID '.$productId.'. '.mb_strimwidth($exception->getMessage(), 0, 300, '…'), $actor, (int) $productId);

            return $material->refresh();
        }
    }

    /**
     * A local unit without a Product Detail ID would make the update payload create a duplicate
     * detail. Before updating, adopt the single active remote detail with the same UOM and SKU.
     *
     * @param  array<string, mixed>  $remoteProduct
     */
    private function backfillMissingDetailIds(RndProductEsbMaterial $material, array $remoteProduct): void
    {
        $details = collect($remoteProduct['productDetails'] ?? [])
            ->filter(fn ($detail): bool => is_array($detail) && (int) ($detail['productDetailID'] ?? 0) > 0
                && (! array_key_exists('flagActive', $detail) || in_array(mb_strtolower((string) $detail['flagActive']), ['1', 'true', 'yes'], true)));

        foreach ($material->units()->whereNull('esb_product_detail_id')->get() as $unit) {
            $matches = $details->filter(fn (array $detail): bool => (int) ($detail['uomID'] ?? $detail['unitID'] ?? 0) === (int) $unit->uom_id
                && mb_strtolower(trim((string) ($detail['sku'] ?? $detail['SKU'] ?? ''))) === mb_strtolower(trim((string) $unit->sku)));
            if ($matches->count() === 1) {
                $unit->update(['esb_product_detail_id' => (int) $matches->first()['productDetailID']]);
            }
        }
    }
}
