<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoMenuSyncStatus;
use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * docs/rnd-internal-memo-prd.md §6, §7.5. A revision copies the prior Finalized memo's metadata
 * and Menu list (including Forecast Quantity and Shelf Life, which are local corrections the
 * user is likely revising for) as a Draft starting point. Materials are not copied — each Menu's
 * `sync_status` is reset to `pending` so the new revision must sync fresh BOM data from ESB
 * before it can reach Ready, rather than trusting a potentially stale snapshot.
 *
 * `memo_number` must stay globally unique (§12.1), so it cannot simply be reused from the prior
 * revision; the caller supplies a new one.
 */
class CreateInternalMemoRevisionAction
{
    public function execute(RndInternalMemo $memo, string $newMemoNumber, User $actor): RndInternalMemo
    {
        if ($memo->status !== RndInternalMemoStatus::Finalized) {
            throw new RuntimeException('Revisi hanya dapat dibuat dari Memo yang sudah Finalized.');
        }

        if (RndInternalMemo::query()->where('memo_number', $newMemoNumber)->exists()) {
            throw ValidationException::withMessages(['memo_number' => 'Nomor Memo sudah digunakan.']);
        }

        return DB::transaction(function () use ($memo, $newMemoNumber, $actor): RndInternalMemo {
            $revision = $memo->replicate(['status', 'source_synced_at', 'snapshot_hash', 'finalized_by', 'finalized_at', 'archived_by', 'archived_at']);
            $revision->memo_number = $newMemoNumber;
            $revision->status = RndInternalMemoStatus::Draft;
            $revision->revision = $memo->revision + 1;
            $revision->created_by = $actor->id;
            $revision->updated_by = null;
            $revision->save();

            foreach ($memo->menus as $menu) {
                $revision->menus()->create([
                    'esb_menu_id' => $menu->esb_menu_id,
                    'menu_code' => $menu->menu_code,
                    'menu_name' => $menu->menu_name,
                    'category_detail' => $menu->category_detail,
                    'esb_bom_id' => $menu->esb_bom_id,
                    'bom_name' => $menu->bom_name,
                    'release_date' => $menu->release_date,
                    'forecast_quantity' => $menu->forecast_quantity,
                    'shelf_life_value' => $menu->shelf_life_value,
                    'shelf_life_unit' => $menu->shelf_life_unit,
                    'storage_condition' => $menu->storage_condition,
                    'shelf_life_notes' => $menu->shelf_life_notes,
                    'sync_status' => RndInternalMemoMenuSyncStatus::Pending,
                    'menu_snapshot' => $menu->menu_snapshot,
                    'sort_order' => $menu->sort_order,
                ]);
            }

            return $revision->fresh();
        });
    }
}
