<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\StockCardEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class StockCardDailySelectionService
{
    public function __construct(private StockCardCategoryFilter $categoryFilter) {}

    /**
     * @param  list<array<string, mixed>>  $products
     * @param  array<string, array<string, mixed>>  $settingsSnapshot
     * @return array{products:list<array<string, mixed>>,summary:array<string, array<string, mixed>>,warnings:list<string>}
     */
    public function select(Branch $branch, string $reportDate, array $products, array $settingsSnapshot): array
    {
        $rules = $this->rules($settingsSnapshot);
        $history = $this->lastUsedDates($branch, $reportDate);
        $selected = collect();
        $summary = [];
        $warnings = [];

        collect($products)
            ->groupBy(fn (array $product): string => $this->categoryFilter->normalizeName(
                trim((string) ($product['category'] ?? '')) ?: 'Tanpa Kategori'
            ))
            ->each(function (Collection $categoryProducts, string $categoryKey) use ($branch, $reportDate, $rules, $history, &$selected, &$summary, &$warnings): void {
                $categoryName = trim((string) ($categoryProducts->first()['category'] ?? '')) ?: 'Tanpa Kategori';
                $rule = $rules[$categoryKey] ?? ['mode' => 'all', 'daily_count' => null, 'rotate_daily' => false];
                $available = $categoryProducts->count();
                $target = $rule['mode'] === 'limited' ? max(1, (int) $rule['daily_count']) : $available;

                if ($rule['mode'] === 'limited' && $available < $target) {
                    $warnings[] = "Target kategori {$categoryName} adalah {$target} produk, tetapi hanya {$available} produk tersedia.";
                }

                $ordered = $categoryProducts->sort(function (array $left, array $right) use ($branch, $reportDate, $categoryKey, $rule, $history): int {
                    if ($rule['rotate_daily']) {
                        $leftUsed = $history[$categoryKey][$left['product_code']] ?? '';
                        $rightUsed = $history[$categoryKey][$right['product_code']] ?? '';
                        $lastUsedComparison = $leftUsed <=> $rightUsed;
                        if ($lastUsedComparison !== 0) {
                            return $lastUsedComparison;
                        }
                    }

                    return strcmp(
                        sha1($branch->id.'|'.$categoryKey.'|'.($rule['rotate_daily'] ? $reportDate : 'fixed').'|'.$left['product_code']),
                        sha1($branch->id.'|'.$categoryKey.'|'.($rule['rotate_daily'] ? $reportDate : 'fixed').'|'.$right['product_code']),
                    );
                })->take(min($target, $available))->values();

                $selected = $selected->concat($ordered);
                $summary[$categoryKey] = [
                    'category' => $categoryName,
                    'mode' => $rule['mode'],
                    'target' => $rule['mode'] === 'limited' ? $target : null,
                    'available' => $available,
                    'selected' => $ordered->count(),
                    'rotate_daily' => (bool) $rule['rotate_daily'],
                ];
            });

        return ['products' => $selected->values()->all(), 'summary' => $summary, 'warnings' => $warnings];
    }

    /** @param array<string, array<string, mixed>> $snapshot
     * @return array<string, array{mode:string,daily_count:?int,rotate_daily:bool}>
     */
    private function rules(array $snapshot): array
    {
        $source = collect($snapshot)->first();

        return collect(is_array($source) ? ($source['category_rules'] ?? []) : [])
            ->mapWithKeys(function (array $rule): array {
                $name = trim((string) ($rule['category_name'] ?? ''));
                if ($name === '') {
                    return [];
                }

                return [$this->categoryFilter->normalizeName($name) => [
                    'mode' => ($rule['mode'] ?? 'all') === 'limited' ? 'limited' : 'all',
                    'daily_count' => isset($rule['daily_count']) ? (int) $rule['daily_count'] : null,
                    'rotate_daily' => (bool) ($rule['rotate_daily'] ?? false),
                ]];
            })->all();
    }

    /** @return array<string, array<string, string>> */
    private function lastUsedDates(Branch $branch, string $reportDate): array
    {
        $history = [];
        $rows = StockCardEntry::query()
            ->join('stock_cards', 'stock_cards.id', '=', 'stock_card_entries.stock_card_id')
            ->where('stock_cards.branch_id', $branch->id)
            ->whereDate('stock_cards.report_date', '<', CarbonImmutable::parse($reportDate)->toDateString())
            ->selectRaw('stock_card_entries.product_category, stock_card_entries.product_code, MAX(stock_cards.report_date) as last_used_at')
            ->groupBy('stock_card_entries.product_category', 'stock_card_entries.product_code')
            ->get();

        foreach ($rows as $row) {
            $category = trim((string) $row->product_category) ?: 'Tanpa Kategori';
            $history[$this->categoryFilter->normalizeName($category)][(string) $row->product_code] = (string) $row->last_used_at;
        }

        return $history;
    }
}
