<?php

namespace App\Console\Commands;

use App\Actions\Briefing\GroupLegacyBriefingBundleAction;
use App\Models\Branch;
use App\Models\BriefingTask;
use App\Services\BriefingLegacyGroupingPlan;
use App\Services\BriefingLegacyGroupingScanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * docs/daily-briefing-bundle-approval-prd.md §15 (Phase 9). Groups legacy briefing rows into
 * canonical bundles. Reports only, unless --execute is passed explicitly together with a snapshot
 * path; this command never adds that flag itself. Each group is applied in its own transaction and
 * appended to the snapshot as soon as it commits, so an interrupted run can be resumed (--resume)
 * or reverted (--restore). Groups already converted are no longer legacy, so re-running never
 * duplicates. Scores are never recomputed here.
 */
#[Signature('briefing:group-legacy-bundles
    {--execute : Actually convert the automatic buckets (default is a read-only report)}
    {--snapshot= : Rollback artifact path on the local disk; required with --execute}
    {--resume : Continue an interrupted run, appending to the existing --snapshot}
    {--branch= : Limit to one branch ID}
    {--year= : Limit to legacy rows dated in this year}
    {--month= : Limit to legacy rows dated in this month (requires --year)}
    {--chunk=500 : Rows read per batch while discovering groups}
    {--restore= : Revert every group recorded in this snapshot}')]
#[Description('Dry-run by default: classifies legacy Daily Briefing rows into bundles. --execute --snapshot=<path> converts the automatic buckets; --restore=<path> reverts them.')]
class BriefingGroupLegacyBundlesCommand extends Command
{
    private const SAMPLE_SIZE = 5;

    public function __construct(
        private readonly BriefingLegacyGroupingScanner $scanner,
        private readonly GroupLegacyBriefingBundleAction $grouping,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($restorePath = $this->option('restore')) {
            return $this->restore($restorePath);
        }

        $execute = (bool) $this->option('execute');
        $snapshotPath = $this->option('snapshot');

        if ($execute && blank($snapshotPath)) {
            $this->error('--execute membutuhkan --snapshot=<path> untuk artefak rollback.');

            return Command::FAILURE;
        }

        if ($execute && Storage::disk('local')->exists($snapshotPath) && ! $this->option('resume')) {
            $this->error("Snapshot {$snapshotPath} sudah ada. Pakai --resume untuk melanjutkan run tersebut, atau path baru.");

            return Command::FAILURE;
        }

        if ($this->option('month') && ! $this->option('year')) {
            $this->error('--month membutuhkan --year.');

            return Command::FAILURE;
        }

        $runId = (string) Str::ulid();
        $this->warn($execute ? "=== EXECUTE (run {$runId}) — kelompok otomatis akan dikonversi ===" : '=== DRY RUN — tidak ada data yang diubah ===');

        $tally = [];
        $samples = [];
        $perBranch = [];
        $applied = 0;
        $skippedStale = 0;
        $draftMissing = [];
        $branchNames = Branch::query()->pluck('name', 'id');

        $groups = $this->scanner->groups(
            $this->option('branch') ? (int) $this->option('branch') : null,
            $this->option('year') ? (int) $this->option('year') : null,
            $this->option('month') ? (int) $this->option('month') : null,
            (int) $this->option('chunk'),
        );

        foreach ($groups as $key => $group) {
            $plan = $this->scanner->plan($group);
            $bucket = $plan->classification.($plan->reason ? " ({$plan->reason})" : '');

            $tally[$bucket] = ($tally[$bucket] ?? 0) + 1;
            $perBranch[$branchNames[$plan->branchId] ?? 'Tanpa cabang'][$plan->classification] = ($perBranch[$branchNames[$plan->branchId] ?? 'Tanpa cabang'][$plan->classification] ?? 0) + 1;

            foreach ($plan->missingTaskKeys as $missingKey) {
                $draftMissing[$missingKey] = ($draftMissing[$missingKey] ?? 0) + 1;
            }

            if (count($samples[$bucket] ?? []) < self::SAMPLE_SIZE) {
                $samples[$bucket][] = $key.' → #'.implode(',#', $plan->sourceRecordIds);
            }

            if (! $execute || ! $plan->isMutating()) {
                continue;
            }

            $entry = $this->grouping->apply($plan, $runId);

            if ($entry === null) {
                $skippedStale++;

                continue;
            }

            Storage::disk('local')->append($snapshotPath, json_encode($entry));
            $applied++;
        }

        $this->report($tally, $perBranch, $samples, $draftMissing);

        Log::info('briefing.group_legacy_bundles.completed', [
            'run_id' => $runId,
            'execute' => $execute,
            'tally' => $tally,
            'applied' => $applied,
            'skipped_stale' => $skippedStale,
            'snapshot_path' => $execute ? $snapshotPath : null,
        ]);

        if ($execute) {
            $this->info("Dikonversi: {$applied} kelompok. Dilewati karena data berubah sejak diklasifikasi: {$skippedStale}.");
            $this->warn("Rollback: php artisan briefing:group-legacy-bundles --restore={$snapshotPath}");
            $this->warn('Skor tidak dihitung ulang. Jalankan compare/recompute secara terpisah setelah ditinjau.');
        } else {
            $this->info('Tidak ada mutation. manual_review dan cleanup_candidate tidak pernah dikonversi otomatis.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param  array<string, int>  $tally
     * @param  array<string, array<string, int>>  $perBranch
     * @param  array<string, list<string>>  $samples
     * @param  array<string, int>  $draftMissing  task key => draft groups missing it
     */
    private function report(array $tally, array $perBranch, array $samples, array $draftMissing = []): void
    {
        ksort($tally);
        $this->table(['Klasifikasi', 'Jumlah Kelompok'], collect($tally)->map(fn (int $count, string $bucket): array => [$bucket, $count])->values()->all());

        $classifications = [...BriefingLegacyGroupingPlan::MUTATING, BriefingLegacyGroupingPlan::MISSING, BriefingLegacyGroupingPlan::CLEANUP_CANDIDATE, BriefingLegacyGroupingPlan::MANUAL_REVIEW];
        $this->table(['Cabang', ...$classifications], collect($perBranch)->map(fn (array $counts, string $branch): array => [$branch, ...array_map(fn (string $c): int => $counts[$c] ?? 0, $classifications)])->values()->all());

        foreach ($samples as $bucket => $lines) {
            $this->line("<comment>{$bucket}</comment> contoh: ".implode('; ', $lines));
        }

        if ($draftMissing !== []) {
            arsort($draftMissing);
            $tasks = BriefingTask::query()->whereIn('key', array_keys($draftMissing))->get(['key', 'label', 'branch_id', 'is_active', 'created_at'])->keyBy('key');

            $this->newLine();
            $this->line('<comment>Poin yang paling sering kurang di kelompok draft</comment>');
            $this->table(['Poin', 'Label', 'Cabang', 'Aktif', 'Dibuat', 'Kelompok draft'], collect($draftMissing)->take(25)->map(fn (int $count, string $key): array => [
                $key,
                $tasks[$key]->label ?? '-',
                $tasks[$key]?->branch_id ?? 'global',
                isset($tasks[$key]) ? ($tasks[$key]->is_active ? 'ya' : 'tidak') : '-',
                $tasks[$key]?->created_at?->format('Y-m-d') ?? '-',
                $count,
            ])->values()->all());
        }
    }

    private function restore(string $path): int
    {
        if (! Storage::disk('local')->exists($path)) {
            $this->error("Snapshot {$path} tidak ditemukan.");

            return Command::FAILURE;
        }

        $entries = collect(explode("\n", trim(Storage::disk('local')->get($path))))
            ->filter()
            ->map(fn (string $line): array => json_decode($line, associative: true))
            ->reverse();

        $entries->each(fn (array $entry) => $this->grouping->restore($entry));

        Log::warning('briefing.group_legacy_bundles.restored', ['snapshot_path' => $path, 'groups' => $entries->count()]);
        $this->info("Dipulihkan {$entries->count()} kelompok dari snapshot {$path}.");

        return Command::SUCCESS;
    }
}
