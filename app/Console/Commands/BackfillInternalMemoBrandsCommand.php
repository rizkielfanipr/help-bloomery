<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\RndInternalMemo;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Backfills `brand_id` + `brand_name_snapshot` on legacy Memos from their legacy Branch rows
 * (docs/rnd-internal-memo-brand-prd.md §16). Dry-run by default; `--apply` writes.
 *
 * A Memo is resolved only when its legacy Branches carry exactly one distinct non-null Brand —
 * never the first, the majority, or the lowest ID. Memos that already have a Brand keep it (only an
 * empty snapshot is filled). Historical non-BLSS Memos are reported and left untouched (§16.4).
 * Each Memo is written in its own short transaction; any per-Memo failure makes the exit code
 * non-zero so the run is reviewed.
 */
class BackfillInternalMemoBrandsCommand extends Command
{
    protected $signature = 'rnd:backfill-internal-memo-brands
        {--apply : Write the resolved Brands (default is a dry-run)}
        {--chunk=200 : Memos per chunk}';

    protected $description = 'Fill the Brand of legacy Memo Internal records from their legacy Branch rows without guessing. Dry-run unless --apply is given.';

    /** @var array<string, int> */
    private array $summary = [
        'scanned' => 0,
        'resolved' => 0,
        'already_resolved' => 0,
        'unresolved_no_brand' => 0,
        'unresolved_multiple_brands' => 0,
        'skipped_non_blss' => 0,
        'failed' => 0,
    ];

    /** @var array<string, list<string>> */
    private array $examples = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $chunk = max(1, (int) $this->option('chunk'));

        $this->info($apply ? 'Mode APPLY: Brand yang terpetakan akan disimpan.' : 'Mode DRY-RUN: tidak ada data yang diubah. Tambahkan --apply untuk menyimpan.');

        try {
            RndInternalMemo::withTrashed()
                ->select(['id', 'company_code', 'brand_id', 'brand_name_snapshot', 'memo_number'])
                ->chunkById($chunk, fn (Collection $memos) => $this->processChunk($memos, $apply));
        } catch (Throwable $exception) {
            $this->error('Backfill dihentikan: '.$exception::class);

            return self::FAILURE;
        }

        $this->table(['Hasil', 'Jumlah', 'Contoh Memo'], collect($this->summary)->map(fn (int $count, string $key): array => [
            $key,
            $count,
            implode(', ', array_slice($this->examples[$key] ?? [], 0, 10)),
        ])->values()->all());

        return $this->summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param Collection<int, RndInternalMemo> $memos */
    private function processChunk(Collection $memos, bool $apply): void
    {
        $brandIdsByMemo = DB::table('rnd_internal_memo_branches')
            ->join('branches', 'branches.id', '=', 'rnd_internal_memo_branches.branch_id')
            ->whereIn('rnd_internal_memo_branches.rnd_internal_memo_id', $memos->modelKeys())
            ->whereNotNull('branches.brand_id')
            ->get(['rnd_internal_memo_branches.rnd_internal_memo_id as memo_id', 'branches.brand_id'])
            ->groupBy('memo_id')
            ->map(fn ($rows): array => $rows->pluck('brand_id')->map(fn ($id): int => (int) $id)->unique()->values()->all());
        $brands = Brand::query()->whereIn('id', $brandIdsByMemo->flatten()->unique()->merge($memos->pluck('brand_id')->filter()))->get()->keyBy('id');

        foreach ($memos as $memo) {
            $this->summary['scanned']++;
            $label = "#{$memo->id} {$memo->memo_number}";

            if ($memo->brand_id !== null) {
                $this->summary['already_resolved']++;
                if (blank($memo->brand_name_snapshot) && $brands->has($memo->brand_id)) {
                    $this->write($memo, $memo->brand_id, $brands[$memo->brand_id]->name, $apply, $label, onlySnapshot: true);
                }

                continue;
            }

            if ($memo->company_code !== RndInternalMemo::COMPANY_CODE) {
                $this->record('skipped_non_blss', $label);

                continue;
            }

            $candidates = $brandIdsByMemo->get($memo->id, []);

            if ($candidates === []) {
                $this->record('unresolved_no_brand', $label);
            } elseif (count($candidates) > 1) {
                $this->record('unresolved_multiple_brands', $label);
            } elseif ($brands->has($candidates[0]) && $this->write($memo, $candidates[0], $brands[$candidates[0]]->name, $apply, $label)) {
                $this->record('resolved', $label);
            }
        }
    }

    private function write(RndInternalMemo $memo, int $brandId, string $brandName, bool $apply, string $label, bool $onlySnapshot = false): bool
    {
        if (! $apply) {
            return true;
        }

        try {
            DB::transaction(function () use ($memo, $brandId, $brandName, $onlySnapshot): void {
                $locked = RndInternalMemo::withTrashed()->whereKey($memo->id)->lockForUpdate()->firstOrFail();

                // Re-checked under lock so a concurrent edit or a re-run never overwrites a Brand.
                if ($onlySnapshot ? $locked->brand_id !== $brandId || filled($locked->brand_name_snapshot) : $locked->brand_id !== null) {
                    return;
                }

                $locked->forceFill(['brand_id' => $brandId, 'brand_name_snapshot' => $brandName])->saveQuietly();
            });

            return true;
        } catch (Throwable $exception) {
            $this->record('failed', $label.' ('.$exception::class.')');

            return false;
        }
    }

    private function record(string $key, string $label): void
    {
        $this->summary[$key]++;
        $this->examples[$key][] = $label;
    }
}
