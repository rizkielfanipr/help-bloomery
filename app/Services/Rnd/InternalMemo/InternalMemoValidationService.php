<?php

namespace App\Services\Rnd\InternalMemo;

use App\Enums\RndInternalMemoMenuSyncStatus;
use App\Models\RndInternalMemo;

/**
 * docs/rnd-internal-memo-prd.md §15. Two catalog items are deliberately not implemented, both
 * documented in the Phase 0 audit and the user's explicit simplification instruction:
 * - "Output yield wajib kosong atau nol" — the app never reads or computes an output yield
 *   at all (unproven field, dropped), so there is nothing to validate.
 * - "Snapshot berubah setelah review tanpa sync ulang" — detecting this would require an extra
 *   ESB call purely to diff freshness, which is out of scope for this pass; deferred.
 */
class InternalMemoValidationService
{
    /**
     * @param  array{rows: list<array<string, mixed>>, warnings: list<string>}|null  $consolidation  Pass an already-computed
     *                                                                                               result (e.g. a caller's own memoized InternalMemoConsolidationService::consolidate() call) to avoid
     *                                                                                               scanning every Material on the memo a second time in the same request; omitted, it is computed here.
     * @return array{blockers: list<string>, warnings: list<string>}
     */
    public function validate(RndInternalMemo $memo, ?array $consolidation = null): array
    {
        $blockers = [];
        $warnings = [];

        $menus = $memo->menus()->get();

        if ($menus->isEmpty()) {
            $blockers[] = 'Memo belum memiliki Menu.';

            return ['blockers' => $blockers, 'warnings' => $warnings];
        }

        // Note: memo-level "Syncing" is deliberately not checked here. This validator is also
        // what RecalculateInternalMemoStatusAction runs to decide what a Syncing memo becomes
        // *next*, while the memo's own status column still reads Syncing at that exact moment —
        // checking it here would make that transition permanently block itself. Per-menu
        // `sync_status` below already covers a Menu still mid-sync or stuck there.
        foreach ($menus as $menu) {
            $label = $menu->menu_name ?: "Menu #{$menu->esb_menu_id}";

            if ((int) $menu->esb_bom_id < 1) {
                $blockers[] = "Menu \"{$label}\" belum memiliki BOM (bomID = 0).";
            }

            if ((float) $menu->forecast_quantity <= 0) {
                $blockers[] = "Forecast Quantity Menu \"{$label}\" belum diisi atau tidak valid.";
            }

            if (! $menu->hasShelfLife()) {
                $blockers[] = "Shelf Life Menu \"{$label}\" belum diisi.";
            }

            match ($menu->sync_status) {
                RndInternalMemoMenuSyncStatus::Pending => $blockers[] = "Menu \"{$label}\" belum pernah disinkronkan.",
                RndInternalMemoMenuSyncStatus::Syncing => $blockers[] = "Menu \"{$label}\" sedang disinkronkan.",
                RndInternalMemoMenuSyncStatus::Failed => $blockers[] = "Sinkronisasi BOM Menu \"{$label}\" gagal: {$menu->sync_error}",
                RndInternalMemoMenuSyncStatus::Synced => null,
            };

            if ($menu->sync_status === RndInternalMemoMenuSyncStatus::Synced && filled($menu->sync_error)) {
                $blockers[] = "Menu \"{$label}\": {$menu->sync_error}";
            }

            foreach ($menu->sync_warnings ?? [] as $warning) {
                $warnings[] = $warning;
            }
        }

        $consolidation ??= app(InternalMemoConsolidationService::class)->consolidate($memo);
        $warnings = [...$warnings, ...$consolidation['warnings']];

        $byProductDetail = collect($consolidation['rows'])
            ->filter(fn (array $row) => ! $row['has_fallback_identity'])
            ->groupBy(fn (array $row) => explode(':', $row['key'])[1] ?? $row['key']);

        foreach ($byProductDetail as $rowsForProduct) {
            if ($rowsForProduct->pluck('uom_name')->unique()->count() > 1) {
                $warnings[] = "Produk \"{$rowsForProduct->first()['product_name']}\" muncul dengan UOM berbeda dan ditampilkan sebagai baris terpisah.";
            }
        }

        return ['blockers' => array_values(array_unique($blockers)), 'warnings' => array_values(array_unique($warnings))];
    }
}
