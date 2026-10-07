<?php

namespace App\Console\Commands;

use App\Services\Rnd\ShelfLife\WipShelfLifeIdentityAudit;
use Illuminate\Console\Command;

/**
 * Dry-run audit of the Shelf Life master table (docs/rnd-wip-shelf-life-prd.md §21.2, §28 step 2).
 * Read-only: it reports conflicts and legacy data but never fixes them.
 */
class AuditWipShelfLifeCommand extends Command
{
    protected $signature = 'rnd:audit-wip-shelf-life {--json : Print the full report as JSON}';

    protected $description = 'Dry-run report of WIP Shelf Life identity conflicts, legacy Menu rows, and unmapped units. Never modifies data.';

    public function handle(WipShelfLifeIdentityAudit $audit): int
    {
        $report = $audit->report();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $report['duplicate_identities'] === [] && $report['trashed_identity_conflicts'] === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->info("Total row (termasuk soft-deleted): {$report['total']}");
        $this->table(['Pemeriksaan', 'Jumlah', 'Contoh ID'], collect([
            'Duplicate company + Product Detail ID (aktif)' => $report['duplicate_identities'],
            'Konflik dengan row soft-deleted' => $report['trashed_identity_conflicts'],
            'Product Detail ID kosong' => $report['missing_product_detail_ids'],
            'Row Menu-only (legacy)' => $report['menu_only'],
            'Row Menu ID + Product Detail ID' => $report['menu_and_product_detail'],
            'Satuan legacy (dapat dipetakan)' => $report['legacy_units'],
            'Satuan tidak dikenal' => $report['unknown_units'],
            'Kondisi penyimpanan tidak dikenal' => $report['unknown_storage_conditions'],
            'Product Detail ID belum terbukti WIP (tidak ada di katalog BOM / produk WIP)' => $report['unproven_wip_product_detail_ids'],
        ])->map(fn (array $items, string $label): array => [
            $label,
            count($items),
            collect($items)->take(10)->map(fn ($item): string => is_array($item) ? json_encode($item) : (string) $item)->implode(', '),
        ])->values()->all());

        $blocking = $report['duplicate_identities'] !== [] || $report['trashed_identity_conflicts'] !== [];

        if ($blocking) {
            $this->error('Ada konflik identity. Selesaikan melalui proses yang disetujui sebelum unique constraint diterapkan.');

            return self::FAILURE;
        }

        $this->info('Tidak ada konflik identity. Data lain di atas hanya dilaporkan dan tidak diubah.');

        return self::SUCCESS;
    }
}
