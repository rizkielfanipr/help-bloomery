<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoStatus;
use App\Models\Branch;
use App\Models\RndInternalMemo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * docs/rnd-internal-memo-multi-branch-prd.md §7.1, §8, §13. Company Code is no longer a fixed
 * constant: the user picks one or more Branch Tujuan (local Master Branch only), and this Action
 * resolves each one's ESB mapping server-side before anything is persisted — access and mapping
 * are re-validated here regardless of what the form already filtered, per §6 "Pilihan UI bukan
 * satu-satunya perlindungan".
 *
 * `rnd_internal_memos.company_code` is kept populated (from the first resolved branch, in
 * selection order) only for backward compatibility with the existing duplicate-period unique
 * index and any code still filtering by it — it is no longer the authoritative Company Code for
 * any individual Menu or branch; `RndInternalMemoBranch`/`RndInternalMemoMenu` carry that.
 */
class CreateInternalMemoAction
{
    public function __construct(private readonly ResolveMemoBranchMappingsAction $resolveBranchMappings) {}

    /** @param array{memo_number: string, title: string, period_month: string, memo_date: string, recipient: string, sender: string, subject: string, notes: ?string, branch_ids: list<int>} $data */
    public function execute(array $data, User $actor): RndInternalMemo
    {
        $branchIds = array_values(array_unique(array_map('intval', $data['branch_ids'] ?? [])));

        if ($branchIds === []) {
            throw ValidationException::withMessages([
                'branch_ids' => 'Pilih minimal satu Branch Tujuan.',
            ]);
        }

        $branches = Branch::query()->whereIn('id', $branchIds)->get()->keyBy('id');

        foreach ($branchIds as $branchId) {
            if (! $branches->has($branchId)) {
                throw ValidationException::withMessages(['branch_ids' => 'Branch yang dipilih tidak ditemukan.']);
            }

            if (! $actor->canAccessBranch($branchId)) {
                throw ValidationException::withMessages([
                    'branch_ids' => "Branch \"{$branches[$branchId]->name}\" tidak dapat diakses.",
                ]);
            }
        }

        $resolutions = $this->resolveBranchMappings->resolveMany($branches->only($branchIds)->values());

        foreach ($resolutions as $branchId => $resolution) {
            if (! $resolution->isResolved()) {
                throw ValidationException::withMessages([
                    'branch_ids' => "Branch \"{$resolution->branch->name}\": {$resolution->blockedReason}",
                ]);
            }
        }

        // Order preserved from the user's own selection so "first resolved branch" below is
        // deterministic and matches what they picked first, not a query/array re-ordering.
        $orderedResolutions = array_map(fn (int $id) => $resolutions[$id], $branchIds);
        $primaryCompanyCode = $orderedResolutions[0]->mapping->esb_comcode;

        if (RndInternalMemo::query()->where('company_code', $primaryCompanyCode)->whereDate('period_month', $data['period_month'])->where('revision', 1)->exists()) {
            throw ValidationException::withMessages([
                'period_month' => 'Memo untuk periode ini sudah ada.',
            ]);
        }

        return DB::transaction(function () use ($data, $actor, $orderedResolutions, $primaryCompanyCode): RndInternalMemo {
            $memo = RndInternalMemo::query()->create([
                'company_code' => $primaryCompanyCode,
                'memo_number' => trim((string) $data['memo_number']),
                'title' => trim((string) $data['title']),
                'period_month' => $data['period_month'],
                'memo_date' => $data['memo_date'],
                'recipient' => trim((string) $data['recipient']),
                'sender' => trim((string) $data['sender']),
                'subject' => trim((string) $data['subject']),
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'status' => RndInternalMemoStatus::Draft,
                'revision' => 1,
                'created_by' => $actor->id,
            ]);

            foreach ($orderedResolutions as $resolution) {
                $memo->branches()->create([
                    'branch_id' => $resolution->branch->id,
                    'branch_esb_code_id' => $resolution->mapping->id,
                    'branch_name_snapshot' => $resolution->branch->name,
                    'company_code_snapshot' => $resolution->mapping->esb_comcode,
                    'branch_code_snapshot' => $resolution->mapping->esb_branch_code,
                    'esb_branch_id_snapshot' => $resolution->mapping->esb_branch_id,
                    'catalog_sync_status' => 'pending',
                ]);
            }

            return $memo;
        });
    }
}
