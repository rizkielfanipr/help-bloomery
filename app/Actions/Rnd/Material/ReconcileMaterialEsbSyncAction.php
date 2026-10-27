<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialEsbSyncStatus;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\EsbMasterProductService;
use App\Services\Rnd\Material\MaterialAuditLogger;
use App\Services\Rnd\Material\MaterialDetailMapper;
use App\Services\Rnd\Material\MaterialEsbSyncFinalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * IT resolves an uncertain ESB outcome (§13.1, §25) with an explicit reason:
 *
 * - `verify`: read the product by ID; if every unit maps, the material becomes `synced`;
 * - `not_applied`: IT confirmed in ESB that nothing was created/changed, so the approval may be
 *   retried safely (`failed`).
 *
 * A `syncing` claim that never finished (worker or request died mid-flight) is treated the same
 * once it is stale, so it can never stay stuck.
 *
 * The mutation is never re-sent from here.
 */
class ReconcileMaterialEsbSyncAction
{
    public function __construct(
        private readonly EsbMasterProductService $esb,
        private readonly MaterialDetailMapper $mapper,
        private readonly MaterialEsbSyncFinalizer $finalizer,
        private readonly MaterialAuditLogger $audit,
    ) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function verify(RndProductEsbMaterial $material, User $actor, int $productId, string $reason): RndProductEsbMaterial
    {
        $this->authorize($material, $actor, $reason);

        try {
            $remote = $this->esb->findProductById($productId);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['product_id' => 'Produk ESB belum dapat dibaca. Coba lagi beberapa saat lagi.']);
        }
        if (! is_array($remote)) {
            throw ValidationException::withMessages(['product_id' => "Product ID {$productId} tidak ditemukan di ESB."]);
        }

        $result = $this->mapper->map($material, $remote);
        if (! $result['ok']) {
            throw ValidationException::withMessages(['product_id' => 'Mapping belum valid: '.implode(' ', $result['errors'])]);
        }

        $this->audit->event($material, 'esb_reconciled_verified', $actor, $reason, metadata: ['esb_product_id' => $productId]);
        $review = RndMaterialReview::query()->where('rnd_product_esb_material_id', $material->id)->where('submission_version', $material->submission_version)->first();

        return $this->finalizer->markSynced($material, $productId, $remote, $result['mapping'], $actor, $review, 'reconciled');
    }

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function markNotApplied(RndProductEsbMaterial $material, User $actor, string $reason): void
    {
        $this->authorize($material, $actor, $reason);

        DB::transaction(function () use ($material, $actor, $reason): void {
            $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);
            $previous = $material->effectiveEsbSyncStatus();
            $material->forceFill([
                'esb_sync_status' => MaterialEsbSyncStatus::Failed,
                'status' => MaterialEsbSyncStatus::Failed->legacyStatus(),
                // A previously verified identity (update of a synced product) is kept; an
                // unconfirmed ID from an uncertain create is cleared.
                'esb_product_id' => $material->esb_verified_at !== null ? $material->esb_product_id : null,
                'sync_error' => 'Dikonfirmasi IT tidak tercatat di ESB: '.$reason,
            ])->save();
            $this->audit->event($material, 'esb_reconciled_not_applied', $actor, $reason, $previous->value, MaterialEsbSyncStatus::Failed->value);
        });
    }

    private function authorize(RndProductEsbMaterial $material, User $actor, string $reason): void
    {
        if (! Gate::forUser($actor)->allows('reconcileEsb', $material)) {
            throw new AuthorizationException('Anda tidak berhak merekonsiliasi ESB Master Bahan.');
        }
        if (! $material->needsEsbReconciliation()) {
            throw ValidationException::withMessages(['esb' => 'Bahan tidak sedang dalam status rekonsiliasi.']);
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Alasan rekonsiliasi wajib diisi minimal 10 karakter.']);
        }
    }
}
