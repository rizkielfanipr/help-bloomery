<?php

namespace App\Console\Commands;

use App\Services\SalesReport\SalesReportLegacyFinanceMigrator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * docs/sales-report-compliment-void-remediation-prd.md §18.4. Dry-run by default; `--execute`
 * converts legacy `PendingFinance` reports with a valid Supervisor approval to `Completed`
 * after writing a snapshot, and `--restore=<snapshot>` undoes one execution without touching
 * reports that changed afterwards.
 */
#[Signature('sales-reports:migrate-legacy-finance-status
    {--execute : Convert the candidates (default is a dry-run preview)}
    {--restore= : Snapshot ID of an execution to restore (dry-run unless --execute)}
    {--force : Skip the confirmation prompt}')]
#[Description('Preview, convert, or restore legacy Finance Review Sales Reports now that Finance approval is removed.')]
class MigrateLegacySalesReportFinanceStatusCommand extends Command
{
    public function handle(SalesReportLegacyFinanceMigrator $migrator): int
    {
        if (filled($this->option('restore'))) {
            return $this->restore($migrator, (string) $this->option('restore'));
        }

        $preview = $migrator->preview();
        $this->info('=== Legacy Finance Review — '.($this->option('execute') ? 'EXECUTE' : 'DRY RUN (no change)').' ===');
        $this->table(['Check', 'Count'], [
            ['Finance Review (legacy) reports', $preview['total']],
            ['With Supervisor reviewer + time', $preview['with_reviewer']],
            ['With supervisor/approved audit', $preview['with_audit']],
            ['With Finance or settlement/MDR data (kept untouched)', $preview['with_finance_or_settlement']],
            ['Candidates (valid Supervisor approval)', count($preview['candidates'])],
            ['Exceptions (left for manual review)', count($preview['exceptions'])],
        ]);
        if ($preview['by_branch_month'] !== []) {
            $this->table(['Branch', 'Month', 'Reports'], $preview['by_branch_month']);
        }
        if ($preview['candidates'] !== []) {
            $this->line('Candidate IDs: '.implode(', ', $preview['candidates']));
        }
        foreach ($preview['exceptions'] as $exception) {
            $this->warn("Exception #{$exception['id']}: {$exception['reason']}");
        }

        if (! $this->option('execute')) {
            return self::SUCCESS;
        }
        if ($preview['candidates'] === []) {
            $this->info('Nothing to convert.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Convert '.count($preview['candidates']).' report(s) to Completed?')) {
            $this->warn('Cancelled. Nothing changed.');

            return self::FAILURE;
        }

        $result = $migrator->execute();
        $this->info('Converted: '.count($result['converted']).'; skipped (changed during the run): '.count($result['skipped']).'.');
        $this->info("Snapshot: {$result['snapshot']} (restore with --restore={$result['snapshot']})");

        return self::SUCCESS;
    }

    private function restore(SalesReportLegacyFinanceMigrator $migrator, string $snapshot): int
    {
        $apply = (bool) $this->option('execute');
        if ($apply && ! $this->option('force') && ! $this->confirm("Restore snapshot {$snapshot}?")) {
            $this->warn('Cancelled. Nothing changed.');

            return self::FAILURE;
        }

        try {
            $result = $migrator->restore($snapshot, $apply);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(($apply ? 'Restored' : 'Would restore').': '.count($result['restored']).'; skipped: '.count($result['skipped']).'; conflicts: '.count($result['conflicts']).'.');
        foreach ($result['conflicts'] as $conflict) {
            $this->warn("Conflict #{$conflict['id']}: {$conflict['reason']}");
        }

        return self::SUCCESS;
    }
}
