<?php

namespace App\Services;

use App\Actions\CalculateBasketSizeAction;
use App\Models\BasketSizeRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Recalculates a shift's basket size from the complete shift window of the branch,
 * independent of when staff submitted the sales report.
 */
class BasketSizeFinalizer
{
    /** Minutes after a shift ends before its sales are assumed to be complete. */
    public const GRACE_MINUTES = 15;

    public function __construct(
        private EsbService $esb,
        private CalculateBasketSizeAction $calculateBasketSize,
    ) {}

    /**
     * The moment from which the sales of the shift are considered complete.
     */
    public function dueAt(BasketSizeRecord $record): ?CarbonImmutable
    {
        $branch = $record->salesReport?->branch;

        if (! $branch) {
            return null;
        }

        [, $endedAt] = $this->esb->shiftBounds(
            $record->report_date->toDateString(),
            ...$branch->salesShiftWindow($record->shift_number),
        );

        return $endedAt->addMinutes(self::GRACE_MINUTES);
    }

    public function isDue(BasketSizeRecord $record, ?CarbonImmutable $now = null): bool
    {
        $dueAt = $this->dueAt($record);

        return $dueAt !== null && ($now ?? now()->toImmutable())->greaterThanOrEqualTo($dueAt);
    }

    /**
     * Finalize every record whose shift has ended; records still running or without ESB are left alone.
     *
     * @param  iterable<int, BasketSizeRecord>  $records
     * @return array{finalized: int, waiting: int, skipped: int, failed: int, errors: array<int, string>}
     */
    public function process(iterable $records, bool $dryRun = false): array
    {
        $now = now()->toImmutable();
        $result = ['finalized' => 0, 'waiting' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        foreach ($records as $record) {
            if (! $record->salesReport?->branch?->hasEsbIntegration()) {
                $result['skipped']++;

                continue;
            }

            if (! $this->isDue($record, $now)) {
                $result['waiting']++;

                continue;
            }

            if ($dryRun) {
                $result['finalized']++;

                continue;
            }

            try {
                $this->finalize($record);
                $result['finalized']++;
            } catch (RuntimeException $exception) {
                $result['failed']++;
                $result['errors'][] = "Record #{$record->id} ({$record->report_date->toDateString()} shift {$record->shift_number}): {$exception->getMessage()}";
            }
        }

        return $result;
    }

    /**
     * @throws RuntimeException when the ESB data of the branch could not be loaded completely
     */
    public function finalize(BasketSizeRecord $record): BasketSizeRecord
    {
        $report = $record->salesReport()->with(['branch.esbCodes', 'branch.activeSalesShifts', 'employees'])->firstOrFail();
        $branch = $report->branch;

        if (! $branch?->hasEsbIntegration()) {
            throw new RuntimeException('Cabang belum memiliki konfigurasi ESB.');
        }

        $summary = $this->esb->getShiftSummaryByLabelForBranch(
            $branch,
            $report->report_date->toDateString(),
            ...$branch->salesShiftWindow($record->shift_number),
        );

        if ($summary['groups'] === [] || collect($summary['groups'])->contains(fn (array $group): bool => ! $group['ok'])) {
            throw new RuntimeException('Data ESB cabang belum dapat dimuat lengkap.');
        }

        return DB::transaction(function () use ($report, $record, $summary): BasketSizeRecord {
            $report->replaceEsbTransactions($record->shift_number, $summary['transactions']);

            $final = $this->calculateBasketSize->execute($report, $record->shift_number);
            $final->update(['finalized_at' => now()]);

            return $final;
        });
    }
}
