<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemo;
use App\Models\User;
use App\Services\Rnd\InternalMemo\InternalMemoValidationService;
use RuntimeException;

/**
 * docs/rnd-internal-memo-prd.md §7.5, §15. Finalizing locks the Memo's already-persisted Menu/
 * Material rows in place (nothing is copied into a separate snapshot column — those rows *are*
 * the snapshot); a `snapshot_hash` is recorded purely so a later read can detect tampering or
 * drift. The memo becomes read-only the moment its status leaves Ready, enforced by every other
 * Action/Policy already gating on status, not by anything special here.
 */
class FinalizeInternalMemoAction
{
    public function __construct(private InternalMemoValidationService $validator) {}

    public function execute(RndInternalMemo $memo, User $actor): RndInternalMemo
    {
        if ($memo->status !== RndInternalMemoStatus::Ready) {
            throw new RuntimeException('Memo hanya dapat difinalisasi dari status Ready.');
        }

        $result = $this->validator->validate($memo);
        if ($result['blockers'] !== []) {
            throw new RuntimeException('Finalisasi dibatalkan, masih ada blocker: '.implode(' | ', $result['blockers']));
        }

        $memo->update([
            'status' => RndInternalMemoStatus::Finalized,
            'finalized_by' => $actor->id,
            'finalized_at' => now(),
            'snapshot_hash' => $this->buildSnapshotHash($memo),
        ]);

        return $memo->refresh();
    }

    private function buildSnapshotHash(RndInternalMemo $memo): string
    {
        $payload = $memo->menus()->with('materials')->get()->map(fn ($menu) => [
            'esb_menu_id' => $menu->esb_menu_id,
            'esb_bom_id' => $menu->esb_bom_id,
            'forecast_quantity' => (string) $menu->forecast_quantity,
            'shelf_life_value' => (string) $menu->shelf_life_value,
            'shelf_life_unit' => $menu->shelf_life_unit,
            'materials' => $menu->materials->map(fn ($material) => [
                'product_code' => $material->product_code,
                'quantity_per_menu' => (string) $material->quantity_per_menu,
                'net_quantity' => (string) $material->net_quantity,
            ])->all(),
        ])->all();

        return hash('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
