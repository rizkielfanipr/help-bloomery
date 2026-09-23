<?php

namespace App\Services;

use App\Models\BranchEsbCode;
use Throwable;

class EsbBranchSyncService
{
    public function __construct(private readonly EsbItemJournalService $esb) {}

    /**
     * @return array{synced:int,unchanged:int,missing:int,ambiguous:int,failed:int,details:array<int,string>}
     */
    public function syncAll(): array
    {
        $result = ['synced' => 0, 'unchanged' => 0, 'missing' => 0, 'ambiguous' => 0, 'failed' => 0, 'details' => []];
        $mappings = BranchEsbCode::query()
            ->with('branch:id,name')
            ->where('is_active', true)
            ->whereNotNull('esb_comcode')
            ->where('esb_comcode', '!=', '')
            ->whereNotNull('esb_branch_code')
            ->where('esb_branch_code', '!=', '')
            ->orderBy('esb_comcode')
            ->orderBy('id')
            ->get();

        foreach ($mappings->groupBy('esb_comcode') as $companyCode => $companyMappings) {
            try {
                $branches = collect($this->esb->refreshBranches((string) $companyCode))
                    ->filter(fn ($branch): bool => is_array($branch) && filled($branch['branchCode'] ?? null))
                    ->groupBy(fn (array $branch): string => mb_strtoupper(trim((string) $branch['branchCode'])));
            } catch (Throwable $exception) {
                report($exception);
                $result['failed'] += $companyMappings->count();
                $result['details'][] = "{$companyCode}: {$exception->getMessage()}";

                continue;
            }

            foreach ($companyMappings as $mapping) {
                $matches = $branches->get(mb_strtoupper(trim($mapping->esb_branch_code)), collect());
                $label = ($mapping->branch?->name ?? 'Branch').' · '.$mapping->esb_branch_code.' · '.$companyCode;

                if ($matches->isEmpty()) {
                    $result['missing']++;
                    $result['details'][] = "{$label}: Branch Code tidak ditemukan.";

                    continue;
                }

                if ($matches->count() > 1) {
                    $result['ambiguous']++;
                    $result['details'][] = "{$label}: ditemukan lebih dari satu hasil.";

                    continue;
                }

                $esbBranchId = filter_var($matches->first()['branchID'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($esbBranchId === false) {
                    $result['failed']++;
                    $result['details'][] = "{$label}: Branch ID dari ESB tidak valid.";

                    continue;
                }

                $changed = (int) $mapping->esb_branch_id !== $esbBranchId;
                $mapping->update(['esb_branch_id' => $esbBranchId, 'esb_synced_at' => now()]);
                $result[$changed ? 'synced' : 'unchanged']++;
            }
        }

        return $result;
    }
}
