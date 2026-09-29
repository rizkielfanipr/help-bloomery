<?php

namespace App\Services\Rnd\Bom;

use App\Models\RndBomChangeLog;
use Illuminate\Support\Collection;

/**
 * Pure diff calculator between two normalized BOM detail snapshots (docs/rnd-bom-adjustment-prd.md
 * §12, §20). Callers pass already-normalized arrays shaped like an ESB BOM detail response
 * (`productDetailID`, `productName`, `productCode`, `uomName`, `is_active`, `bomDetails`) —
 * this class never fetches data or knows about ESB field-naming quirks.
 */
class BomChangeComparator
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{
     *     components_added: list<array<string, mixed>>,
     *     components_removed: list<array<string, mixed>>,
     *     components_changed: list<array{productDetailID: int, productCode: string, productName: string, uomName: string, before_qty: float, after_qty: float}>,
     *     product_result_changed: bool,
     *     product_result: array{before: array<string, mixed>, after: array<string, mixed>}|null,
     *     unit_changed: bool,
     *     unit: array{before: ?string, after: ?string}|null,
     *     status_changed: bool,
     *     status: array{before: ?bool, after: ?bool}|null,
     *     has_changes: bool,
     * }
     */
    public function compare(array $before, array $after): array
    {
        $beforeComponents = $this->componentsByProductDetailId($before);
        $afterComponents = $this->componentsByProductDetailId($after);

        $added = $afterComponents->except($beforeComponents->keys()->all())->values()->all();
        $removed = $beforeComponents->except($afterComponents->keys()->all())->values()->all();

        $changed = $beforeComponents->intersectByKeys($afterComponents->all())
            ->map(function (array $beforeComponent, int $productDetailId) use ($afterComponents): ?array {
                $afterComponent = $afterComponents->get($productDetailId);
                $beforeQty = (float) ($beforeComponent['qty'] ?? 0);
                $afterQty = (float) ($afterComponent['qty'] ?? 0);

                if ($beforeQty === $afterQty) {
                    return null;
                }

                return [
                    'productDetailID' => $productDetailId,
                    'productCode' => (string) ($afterComponent['productCode'] ?? $beforeComponent['productCode'] ?? ''),
                    'productName' => (string) ($afterComponent['productName'] ?? $beforeComponent['productName'] ?? ''),
                    'uomName' => (string) ($afterComponent['uomName'] ?? $beforeComponent['uomName'] ?? ''),
                    'before_qty' => $beforeQty,
                    'after_qty' => $afterQty,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $productResultChanged = (int) ($before['productDetailID'] ?? 0) !== (int) ($after['productDetailID'] ?? 0);
        $unitBefore = $before['uomName'] ?? null;
        $unitAfter = $after['uomName'] ?? null;
        $unitChanged = $unitBefore !== $unitAfter;
        $statusBefore = array_key_exists('is_active', $before) ? (bool) $before['is_active'] : null;
        $statusAfter = array_key_exists('is_active', $after) ? (bool) $after['is_active'] : null;
        $statusChanged = $statusBefore !== null && $statusAfter !== null && $statusBefore !== $statusAfter;

        return [
            'components_added' => $added,
            'components_removed' => $removed,
            'components_changed' => $changed,
            'product_result_changed' => $productResultChanged,
            'product_result' => $productResultChanged ? [
                'before' => $this->productResult($before),
                'after' => $this->productResult($after),
            ] : null,
            'unit_changed' => $unitChanged,
            'unit' => $unitChanged ? ['before' => $unitBefore, 'after' => $unitAfter] : null,
            'status_changed' => $statusChanged,
            'status' => $statusChanged ? ['before' => $statusBefore, 'after' => $statusAfter] : null,
            'has_changes' => $added !== [] || $removed !== [] || $changed !== []
                || $productResultChanged || $unitChanged || $statusChanged,
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return Collection<int, array<string, mixed>>
     */
    private function componentsByProductDetailId(array $detail): Collection
    {
        return collect($detail['bomDetails'] ?? [])
            ->filter(fn (array $component): bool => (int) ($component['productDetailID'] ?? 0) > 0)
            ->keyBy(fn (array $component): int => (int) $component['productDetailID']);
    }

    /**
     * Verified `changes` (from a Success/reconciled attempt) are preferred; when only a
     * before/requested pair is available (e.g. a Pending or Failed attempt that never reached a
     * confirmed after-state), the same diff is computed live so callers always have something
     * concrete to show instead of nothing.
     *
     * @return array{diff: array<string, mixed>|null, is_confirmed: bool, rows: list<array{product: string, unit: string, before: ?float, after: ?float, note: string}>}
     */
    public function diffRowsForLog(RndBomChangeLog $log): array
    {
        $isConfirmed = is_array($log->changes);
        $diff = $log->changes;

        if ($diff === null && is_array($log->before_snapshot) && is_array($log->requested_snapshot)) {
            $diff = $this->compare($log->before_snapshot, $log->requested_snapshot);
        }

        $rows = [];

        if (is_array($diff)) {
            foreach ($diff['components_added'] ?? [] as $component) {
                $rows[] = [
                    'product' => (string) ($component['productName'] ?? $component['productCode'] ?? 'Component'),
                    'unit' => (string) ($component['uomName'] ?? '-'),
                    'before' => null,
                    'after' => (float) ($component['qty'] ?? 0),
                    'note' => 'Added',
                ];
            }

            foreach ($diff['components_removed'] ?? [] as $component) {
                $rows[] = [
                    'product' => (string) ($component['productName'] ?? $component['productCode'] ?? 'Component'),
                    'unit' => (string) ($component['uomName'] ?? '-'),
                    'before' => (float) ($component['qty'] ?? 0),
                    'after' => null,
                    'note' => 'Removed',
                ];
            }

            foreach ($diff['components_changed'] ?? [] as $component) {
                $rows[] = [
                    'product' => (string) ($component['productName'] ?? $component['productCode'] ?? 'Component'),
                    'unit' => (string) ($component['uomName'] ?? '-'),
                    'before' => (float) ($component['before_qty'] ?? 0),
                    'after' => (float) ($component['after_qty'] ?? 0),
                    'note' => 'Qty Changed',
                ];
            }
        }

        return [
            'diff' => $diff,
            'is_confirmed' => $isConfirmed,
            'rows' => $rows,
        ];
    }

    /** @param  array<string, mixed>  $detail
     * @return array<string, mixed> */
    private function productResult(array $detail): array
    {
        return [
            'productDetailID' => (int) ($detail['productDetailID'] ?? 0),
            'productCode' => (string) ($detail['productCode'] ?? ''),
            'productName' => (string) ($detail['productName'] ?? ''),
        ];
    }
}
