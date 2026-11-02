<?php

namespace App\Console\Commands;

use App\Enums\MaterialSampleCycleStatus;
use App\Enums\MaterialSourcingStatus;
use App\Models\RndMaterialAuditLog;
use App\Models\RndMaterialSampleCycle;
use App\Models\RndProductEsbMaterial;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Fills `sourcing_requested_at` and `sourcing_completed_at` from provable sources only
 * (docs/products/purchasing/sourcing-duration-tracking/prd.md §20). Dry-run by default; `--apply` writes.
 *
 * `updated_at` is never a source and is not touched, because it drives the default queue sort. Only
 * null fields are written and a row whose status changed since it was read is left alone, so the
 * command can run again safely. Output is aggregate counters only: no product, supplier, or user
 * identity. Ambiguous records are expected and do not fail the run.
 */
class BackfillMaterialSourcingDurationsCommand extends Command
{
    /** Events showing that a sourcing decision was undone. */
    private const REOPEN_EVENTS = ['sample_approval_reopened', 'sourcing_reopened', 'legacy_sourcing_resubmitted'];

    protected $signature = 'rnd:backfill-sourcing-durations
        {--apply : Write the proven timestamps (default is a dry-run)}
        {--chunk=200 : Materials per chunk}';

    protected $description = 'Fill sourcing start and completion dates from provable sources without guessing. Dry-run unless --apply is given.';

    /** @var array<string, int> */
    private array $summary = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $chunk = max(1, (int) $this->option('chunk'));
        $this->summary = array_fill_keys([
            'scanned', 'eligible', 'updated', 'skipped_ambiguous', 'invalid_chronology', 'unchanged', 'failed',
            'requested_at_candidates', 'requested_at_ambiguous', 'completed_at_candidates', 'completed_at_ambiguous',
        ], 0);

        $this->info($apply ? 'Mode APPLY: timestamp yang terbukti akan disimpan.' : 'Mode DRY-RUN: tidak ada data yang diubah. Tambahkan --apply untuk menyimpan.');

        try {
            RndProductEsbMaterial::query()
                ->select(['id', 'sourcing_status', 'sourcing_origin', 'sourcing_requested_at', 'sourcing_completed_at', 'rnd_reviewed_at', 'finance_reviewed_at', 'created_at'])
                ->whereNotIn('sourcing_status', array_map(fn (MaterialSourcingStatus $status): string => $status->value, MaterialSourcingStatus::withoutDurationCases()))
                ->chunkById($chunk, fn (Collection $materials) => $this->processChunk($materials, $apply));
        } catch (Throwable $exception) {
            $this->error('Backfill dihentikan: '.$exception::class);

            return self::FAILURE;
        }

        $this->table(['Hasil', 'Jumlah'], collect($this->summary)->map(fn (int $count, string $key): array => [$key, $count])->values()->all());

        return $this->summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param Collection<int, RndProductEsbMaterial> $materials */
    private function processChunk(Collection $materials, bool $apply): void
    {
        $latestCycles = RndMaterialSampleCycle::query()
            ->select(['id', 'rnd_product_esb_material_id', 'cycle_number', 'status', 'evaluated_at'])
            ->whereIn('rnd_product_esb_material_id', $materials->modelKeys())
            ->orderBy('cycle_number')
            ->get()
            ->keyBy('rnd_product_esb_material_id');
        $latestReopens = RndMaterialAuditLog::query()
            ->whereIn('rnd_product_esb_material_id', $materials->modelKeys())
            ->whereIn('event', self::REOPEN_EVENTS)
            ->groupBy('rnd_product_esb_material_id')
            ->selectRaw('rnd_product_esb_material_id, MAX(created_at) as latest_reopen')
            ->pluck('latest_reopen', 'rnd_product_esb_material_id');

        foreach ($materials as $material) {
            $this->summary['scanned']++;
            $changes = [];
            $ambiguous = false;

            $startedAt = $material->sourcing_requested_at;
            if ($startedAt === null) {
                $startedAt = $material->isStandalone() ? $material->created_at : null;
                if ($startedAt !== null) {
                    $changes['sourcing_requested_at'] = $startedAt;
                    $this->summary['requested_at_candidates']++;
                } else {
                    $ambiguous = true;
                    $this->summary['requested_at_ambiguous']++;
                }
            }

            $completedAt = $material->sourcing_completed_at;
            $invalid = $completedAt !== null && $startedAt !== null && $completedAt->lt($startedAt);

            if ($completedAt === null && $material->sourcing_status->isSourcingFinal()) {
                $source = $this->completionSource($material, $latestCycles->get($material->id), $latestReopens->get($material->id));

                if ($source === null) {
                    $ambiguous = true;
                    $this->summary['completed_at_ambiguous']++;
                } elseif ($startedAt !== null && $source->lt($startedAt)) {
                    $invalid = true;
                } else {
                    $changes['sourcing_completed_at'] = $source;
                    $this->summary['completed_at_candidates']++;
                }
            }

            if ($changes !== []) {
                $this->summary['eligible']++;
                if ($apply) {
                    $this->write($material, $changes);
                }
            }
            $this->summary['skipped_ambiguous'] += (int) $ambiguous;
            $this->summary['invalid_chronology'] += (int) $invalid;
            $this->summary['unchanged'] += (int) ($changes === [] && ! $ambiguous && ! $invalid);
        }
    }

    /**
     * The moment the current final decision was made, or null when it cannot be proven: the source
     * is empty, the newest sample cycle does not match the status, or a reopen happened afterwards.
     */
    private function completionSource(RndProductEsbMaterial $material, ?RndMaterialSampleCycle $latestCycle, ?string $latestReopen): ?CarbonInterface
    {
        $source = match ($material->sourcing_status) {
            MaterialSourcingStatus::ApprovedByRnd => $latestCycle?->status === MaterialSampleCycleStatus::Approved ? $latestCycle->evaluated_at : null,
            MaterialSourcingStatus::RejectedByRnd => $latestCycle?->status === MaterialSampleCycleStatus::Rejected ? $latestCycle->evaluated_at : null,
            MaterialSourcingStatus::Approved => $material->finance_reviewed_at,
            MaterialSourcingStatus::Rejected => $material->rnd_reviewed_at,
            default => null,
        };

        if ($source !== null && $latestReopen !== null && Carbon::parse($latestReopen)->gte($source)) {
            return null;
        }

        return $source;
    }

    /** @param array<string, CarbonInterface> $changes */
    private function write(RndProductEsbMaterial $material, array $changes): void
    {
        try {
            $written = DB::transaction(function () use ($material, $changes): int {
                $written = 0;
                foreach ($changes as $column => $value) {
                    $written += RndProductEsbMaterial::query()->toBase()
                        ->where('id', $material->id)
                        ->where('sourcing_status', $material->sourcing_status->value)
                        ->whereNull($column)
                        ->update([$column => $value]);
                }

                return $written;
            });

            $this->summary['updated'] += (int) ($written > 0);
        } catch (Throwable) {
            $this->summary['failed']++;
        }
    }
}
