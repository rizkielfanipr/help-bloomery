<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialEsbSyncStatus;
use App\Enums\MaterialReviewStatus;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\EsbMasterProductService;
use App\Services\Rnd\Material\MaterialAuditLogger;
use App\Services\Rnd\Material\MaterialDetailMapper;
use App\Services\Rnd\Material\MaterialEsbSyncFinalizer;
use App\Services\Rnd\Material\MaterialSnapshot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * IT links the claimed submission to a product that already exists in ESB instead of creating it
 * (§13.2). The product is read by ID first (outside any transaction) and every active unit must
 * map to one of its active Product Details; a name match alone is never enough. Nothing is
 * written to ESB.
 */
class LinkMaterialToExistingEsbProductAction
{
    use ClaimedReview;

    public function __construct(
        private readonly EsbMasterProductService $esb,
        private readonly MaterialDetailMapper $mapper,
        private readonly MaterialSnapshot $snapshot,
        private readonly MaterialAuditLogger $audit,
        private readonly MaterialEsbSyncFinalizer $finalizer,
    ) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor, int $productId, ?int $expectedVersion = null, ?string $note = null): RndProductEsbMaterial
    {
        if (! Gate::forUser($actor)->allows('approveEsb', $material)) {
            throw new AuthorizationException('Anda tidak berhak menautkan produk ESB.');
        }

        try {
            $remote = $this->esb->findProductById($productId);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['product_id' => 'Produk ESB belum dapat dibaca. Coba lagi beberapa saat lagi.']);
        }
        if (! is_array($remote)) {
            throw ValidationException::withMessages(['product_id' => "Product ID {$productId} tidak ditemukan atau tidak aktif di ESB."]);
        }

        $result = $this->mapper->map($material, $remote);
        if (! $result['ok']) {
            throw ValidationException::withMessages(['product_id' => 'Produk tidak cocok: '.implode(' ', $result['errors'])]);
        }

        $review = DB::transaction(function () use ($material, $actor, $productId, $expectedVersion, $note): RndMaterialReview {
            [$material, $review] = $this->lockClaimedReview($material, $actor, $expectedVersion);
            if (! hash_equals($review->snapshot_hash, $this->snapshot->hashFor($material))) {
                throw ValidationException::withMessages(['review' => 'Data bahan berubah sejak diajukan; review ulang sebelum menautkan.']);
            }

            $review->forceFill([
                'status' => MaterialReviewStatus::ApprovedForEsb,
                'decision' => RndMaterialReview::DECISION_APPROVE_LINK,
                'linked_esb_product_id' => $productId,
                'reason' => $note,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'active_material_id' => null,
            ])->save();
            $material->forceFill(['review_status' => MaterialReviewStatus::ApprovedForEsb, 'esb_sync_status' => MaterialEsbSyncStatus::Syncing, 'status' => 'syncing'])->save();
            $this->audit->event($material, 'approved_link_existing', $actor, $note, MaterialReviewStatus::UnderItReview->value, MaterialReviewStatus::ApprovedForEsb->value, ['esb_product_id' => $productId], $review);

            return $review;
        });

        return $this->finalizer->markSynced($material, $productId, $remote, $result['mapping'], $actor, $review, 'linked');
    }
}
