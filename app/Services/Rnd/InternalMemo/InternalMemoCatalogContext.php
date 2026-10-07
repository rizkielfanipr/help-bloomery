<?php

namespace App\Services\Rnd\InternalMemo;

use App\Exceptions\Rnd\InternalMemoCatalogUnavailableException;
use App\Jobs\SyncInternalMemoMenuCatalogJob;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoCatalogSync;
use App\Services\EsbItemJournalService;

/**
 * The single, fixed source of the Memo Internal Master Menu catalog
 * (docs/rnd-internal-memo-brand-prd.md §8.3, §14). Company Code is always BLSS. The ESB endpoint
 * also needs a branchCode; it is discovered from ESB's own BLSS branch list by a fixed rule —
 * never from a Brand, a Memo Branch, the user, or Livewire state — and is never shown in the UI.
 *
 * Only the sync job talks to ESB. Pages read the branch the last successful sync used from the
 * local sync state, so rendering never triggers an ESB request.
 */
class InternalMemoCatalogContext
{
    public const STALE_AFTER_MINUTES = 15;

    /** A queued/running state older than this is treated as abandoned (e.g. a lost worker). */
    public const IN_PROGRESS_TIMEOUT_MINUTES = 10;

    /** How many BLSS branches the job may try before giving up. */
    public const MAX_BRANCH_CANDIDATES = 3;

    public function __construct(private readonly EsbItemJournalService $esb) {}

    public function companyCode(): string
    {
        return RndInternalMemo::COMPANY_CODE;
    }

    /**
     * BLSS branch codes to read the catalog from, in a fixed order: ESB's branch list sorted by
     * code. The Master Menu catalog is the same for every branch of one company (verified for
     * BLS/BLP/BLEV in docs/rnd-internal-memo-multi-branch-phase0-report.md §5), so the first one
     * that answers with active Menus is used; the next ones are only a fallback.
     *
     * @return list<string>
     *
     * @throws InternalMemoCatalogUnavailableException
     */
    public function candidateBranchCodes(): array
    {
        $codes = collect($this->esb->branches($this->companyCode()))
            ->map(fn ($branch): string => is_array($branch) ? mb_strtoupper(trim((string) ($branch['branchCode'] ?? ''))) : '')
            ->filter()
            ->unique()
            ->sort(SORT_STRING)
            ->take(self::MAX_BRANCH_CANDIDATES)
            ->values()
            ->all();

        if ($codes === []) {
            throw new InternalMemoCatalogUnavailableException('ESB tidak mengembalikan cabang BLSS untuk membaca Master Menu.');
        }

        return $codes;
    }

    public function state(): ?RndInternalMemoCatalogSync
    {
        return RndInternalMemoCatalogSync::query()->where('company_code', $this->companyCode())->first();
    }

    /** The branch whose local snapshot the picker reads: the one the last successful sync used. */
    public function catalogBranchCode(): ?string
    {
        $state = $this->state();

        return $state?->last_synced_at !== null && filled($state->technical_branch_code)
            ? $state->technical_branch_code
            : null;
    }

    public function isStale(?RndInternalMemoCatalogSync $state): bool
    {
        return $state?->last_synced_at === null
            || $state->status === RndInternalMemoCatalogSync::STATUS_FAILED
            || $state->last_synced_at->lt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    /**
     * Queues one global catalog sync unless one is already queued/running, or the snapshot is still
     * fresh (unless forced). Returns whether a job was queued. Never runs ESB requests inline.
     */
    public function requestSync(?int $triggeredBy, bool $force = false): bool
    {
        $state = RndInternalMemoCatalogSync::query()->firstOrCreate(['company_code' => $this->companyCode()]);

        $inProgress = $state->isInProgress()
            && $state->updated_at?->gt(now()->subMinutes(self::IN_PROGRESS_TIMEOUT_MINUTES));

        if ($inProgress || (! $force && ! $this->isStale($state))) {
            return false;
        }

        $state->update(['status' => RndInternalMemoCatalogSync::STATUS_QUEUED, 'triggered_by' => $triggeredBy]);
        SyncInternalMemoMenuCatalogJob::dispatch($triggeredBy)->afterCommit();

        return true;
    }
}
