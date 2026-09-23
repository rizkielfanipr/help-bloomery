<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\StockCardSetting;
use Illuminate\Support\Str;

class StockCardCategoryFilter
{
    /** @return array<string, array{all_categories:bool,categories:list<string>,show_uncategorized:bool,normalize_names:bool}> */
    public function snapshot(Branch $branch): array
    {
        $company = $branch->activeStockCardEsbCode()?->esb_comcode;
        $companies = collect($company ? [$company] : []);
        $setting = StockCardSetting::where('company_code', StockCardSetting::GLOBAL_COMPANY)->first();

        return $companies->mapWithKeys(fn (string $company): array => [$company => [
            'all_categories' => $setting?->all_categories ?? true,
            'categories' => $setting?->categories ?? [],
            'show_uncategorized' => $setting?->show_uncategorized ?? true,
            'normalize_names' => true,
            'category_rules' => $setting?->category_rules ?? [],
        ]])->all();
    }

    public function normalizeName(string $name): string
    {
        return mb_strtolower(Str::squish($name));
    }

    /** @param array<string, mixed> $rule */
    private function categoryMatches(string $category, array $rule): bool
    {
        $selected = $rule['categories'] ?? [];
        if ($rule['normalize_names'] ?? false) {
            return in_array($this->normalizeName($category), array_map($this->normalizeName(...), $selected), true);
        }

        return in_array($category, $selected, true);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @param  array<string, array<string, mixed>>  $snapshot
     * @return list<array<string, mixed>>
     */
    public function filter(array $products, array $snapshot): array
    {
        if ($snapshot === []) {
            return $products;
        }

        return array_values(array_filter($products, function (array $product) use ($snapshot): bool {
            $sources = $product['category_sources'] ?? [];
            if ($sources === []) {
                if (count($snapshot) !== 1) {
                    return false;
                }
                $sources = [array_key_first($snapshot) => $product['category'] ?? ''];
            }
            foreach ($sources as $company => $category) {
                if (! isset($snapshot[$company])) {
                    continue;
                }
                $rule = $snapshot[$company];
                $category = trim((string) $category);
                if ($category === '' || $category === 'Tanpa Kategori') {
                    if ($rule['show_uncategorized'] ?? true) {
                        return true;
                    }
                } elseif (($rule['all_categories'] ?? true) || $this->categoryMatches($category, $rule)) {
                    return true;
                }
            }

            return false;
        }));
    }
}
