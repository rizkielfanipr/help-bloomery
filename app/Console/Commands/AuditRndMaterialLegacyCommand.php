<?php

namespace App\Console\Commands;

use App\Enums\MaterialSourcingStatus;
use App\Models\RndProductEsbMaterial;
use App\Models\RndProductEsbMaterialUnit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dry-run classification of Master Bahan rows created before the IT review workflow
 * (docs/material-sourcing-lifecycle-prd.md §26). Read-only and local-only: it never calls ESB,
 * never writes a review, sample evidence or Pricing Request, and never marks a row verified.
 */
class AuditRndMaterialLegacyCommand extends Command
{
    protected $signature = 'rnd:audit-material-legacy
        {--id=* : Only these material IDs}
        {--from-id= : Lowest material ID to include}
        {--to-id= : Highest material ID to include}
        {--json : Print the full report as JSON}';

    protected $description = 'Dry-run report of legacy Master Bahan rows (no IT review history) grouped by what a later, separately approved backfill could safely do. Never modifies data or calls ESB.';

    /** @var array<string, string> */
    private const CATEGORIES = [
        'synced_mapping_complete' => 'Synced lama, semua unit aktif punya Product Detail ID (perlu read-back sebelum ditandai verified)',
        'synced_mapping_incomplete' => 'Synced lama, ada unit aktif tanpa Product Detail ID (review manual)',
        'synced_wip_pricing_candidate' => 'WIP synced lama dengan mapping lengkap (kandidat Pricing Request, dry-run terpisah)',
        'needs_reconciliation' => 'Status unknown lama (perlu rekonsiliasi IT)',
        'failed' => 'Gagal lama (dapat diajukan ulang lewat review IT)',
        'never_sent' => 'Draft lama yang belum pernah dikirim (masuk alur baru saat diajukan)',
        'sourcing_approved_without_evidence' => 'Sourcing lama approved tanpa foto handover/receipt (tidak dijadikan bukti sampel)',
        'standalone_sourcing' => 'Sourcing mandiri tanpa Master Bahan (tetap alur lama, keputusan PRD §32 terbuka)',
        'ambiguous' => 'Tidak dapat diklasifikasikan otomatis (review manual)',
    ];

    public function handle(): int
    {
        $report = array_fill_keys(array_keys(self::CATEGORIES), []);
        $total = 0;

        $this->legacyQuery()->with('units')->chunkById(200, function ($materials) use (&$report, &$total): void {
            foreach ($materials as $material) {
                $total++;
                foreach ($this->classify($material) as $category) {
                    $report[$category][] = $material->id;
                }
            }
        });

        if ($this->option('json')) {
            $this->line(json_encode(['total' => $total, 'categories' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info("DRY RUN — Master Bahan legacy (tanpa review IT): {$total} row. Tidak ada data yang diubah dan tidak ada call ESB.");
        $this->table(['Kategori', 'Jumlah', 'Contoh ID'], collect(self::CATEGORIES)->map(fn (string $label, string $key): array => [
            $label,
            count($report[$key]),
            implode(', ', array_slice($report[$key], 0, 10)),
        ])->values()->all());

        return self::SUCCESS;
    }

    /**
     * @return Builder<RndProductEsbMaterial>
     */
    private function legacyQuery()
    {
        $ids = array_filter(array_map('intval', (array) $this->option('id')));

        return RndProductEsbMaterial::query()
            ->whereNull('review_status')
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->when($this->option('from-id'), fn ($query, $from) => $query->where('id', '>=', (int) $from))
            ->when($this->option('to-id'), fn ($query, $to) => $query->where('id', '<=', (int) $to));
    }

    /**
     * @return list<string>
     */
    private function classify(RndProductEsbMaterial $material): array
    {
        if ($material->isStandalone()) {
            return ['standalone_sourcing'];
        }

        $categories = [];
        if (in_array($material->sourcing_status, [MaterialSourcingStatus::Approved, MaterialSourcingStatus::ApprovedByRnd], true)) {
            $categories[] = 'sourcing_approved_without_evidence';
        }

        $activeUnits = $material->units->filter(fn (RndProductEsbMaterialUnit $unit): bool => (bool) $unit->flag_active);
        $mappingComplete = $activeUnits->isNotEmpty() && $activeUnits->every(fn (RndProductEsbMaterialUnit $unit): bool => (int) $unit->esb_product_detail_id > 0);

        $categories[] = match (true) {
            $material->status === 'synced' && (int) $material->esb_product_id > 0 && $mappingComplete => 'synced_mapping_complete',
            $material->status === 'synced' => 'synced_mapping_incomplete',
            $material->status === 'unknown' => 'needs_reconciliation',
            $material->status === 'failed' => 'failed',
            $material->status === 'draft' && ! $material->esb_product_id => 'never_sent',
            default => 'ambiguous',
        };

        if (end($categories) === 'synced_mapping_complete' && $material->isWip()) {
            $categories[] = 'synced_wip_pricing_candidate';
        }

        return $categories;
    }
}
