<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Models\Branch;
use App\Models\RndInternalMemo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @deprecated Multi-branch flow (docs/rnd-internal-memo-multi-branch-prd.md). No active page calls
 * it since the Brand flow (docs/rnd-internal-memo-brand-prd.md §15.2); kept only until the separate
 * cleanup phase removes the legacy Branch tables. Use UpdateInternalMemoBrandAction.
 */
class UpdateInternalMemoBranchesAction
{
    public function __construct(private readonly ResolveMemoBranchMappingsAction $resolveMappings) {}

    /** @param list<int> $branchIds */
    public function execute(RndInternalMemo $memo, array $branchIds, User $actor): RndInternalMemo
    {
        $branchIds = array_values(array_unique(array_map('intval', $branchIds)));
        if ($branchIds === []) {
            throw ValidationException::withMessages(['branchIds' => 'Pilih minimal satu Branch Tujuan.']);
        }

        $branches = Branch::query()->whereIn('id', $branchIds)->get()->keyBy('id');
        foreach ($branchIds as $branchId) {
            if (! $branches->has($branchId) || ! $actor->canAccessBranch($branchId)) {
                throw ValidationException::withMessages(['branchIds' => 'Terdapat Branch Tujuan yang tidak tersedia atau tidak dapat diakses.']);
            }
        }

        $resolutions = $this->resolveMappings->resolveMany($branches->only($branchIds)->values());
        foreach ($resolutions as $resolution) {
            if (! $resolution->isResolved()) {
                throw ValidationException::withMessages([
                    'branchIds' => "Branch \"{$resolution->branch->name}\": {$resolution->blockedReason}",
                ]);
            }
        }

        $existing = $memo->branches()->get();
        $removedIds = $existing->whereNotIn('branch_id', $branchIds)->pluck('id');
        if ($removedIds->isNotEmpty()) {
            $orphanedMenus = $memo->menus()
                ->whereHas('branches', fn ($query) => $query->whereIn('rnd_internal_memo_branches.id', $removedIds))
                ->whereDoesntHave('branches', fn ($query) => $query->whereNotIn('rnd_internal_memo_branches.id', $removedIds))
                ->pluck('menu_name');

            if ($orphanedMenus->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'branchIds' => 'Lepaskan Menu yang hanya tersedia pada branch tersebut terlebih dahulu: '.$orphanedMenus->implode(', ').'.',
                ]);
            }
        }

        DB::transaction(function () use ($memo, $branchIds, $resolutions, $existing, $removedIds): array {
            $memo->branches()->whereIn('id', $removedIds)->delete();
            $existingBranchIds = $existing->pluck('branch_id')->map(fn ($id): int => (int) $id)->all();
            $added = [];

            foreach ($branchIds as $branchId) {
                if (in_array($branchId, $existingBranchIds, true)) {
                    continue;
                }

                $resolution = $resolutions[$branchId];
                $created = $memo->branches()->create([
                    'branch_id' => $resolution->branch->id,
                    'branch_esb_code_id' => $resolution->mapping->id,
                    'branch_name_snapshot' => $resolution->branch->name,
                    'company_code_snapshot' => $resolution->mapping->esb_comcode,
                    'branch_code_snapshot' => $resolution->mapping->esb_branch_code,
                    'esb_branch_id_snapshot' => $resolution->mapping->esb_branch_id,
                    'catalog_sync_status' => 'pending',
                ]);
                $added[] = [$created->company_code_snapshot, $created->branch_code_snapshot];
            }

            $first = $memo->branches()->orderBy('id')->first();
            if ($first) {
                $memo->update(['company_code' => $first->company_code_snapshot]);
            }

            return $added;
        });

        // The Master Menu catalog is global (BLSS) now; no per-Branch sync is queued from here.

        return $memo->fresh(['branches']);
    }
}
