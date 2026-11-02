<?php

namespace App\Actions\Inventory\StockCard;

use App\Enums\StockCardStatus;
use App\Models\StockCard;
use App\Models\StockCardEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PrepareStockCardDraftAction
{
    /**
     * Create the branch/date draft and its product list without any counted quantity. The first
     * preparer wins: another device's entries are kept, because restarting them could drop a count.
     *
     * @param  list<array{product_code: string, product_name: string, product_category: ?string, system_unit: ?string}>  $products
     * @param  array<int|string, mixed>  $categorySettingsSnapshot
     * @param  array<string, mixed>  $selectionSnapshot
     */
    public function execute(User $actor, string $reportDate, string $flagUnit, array $products, array $categorySettingsSnapshot, array $selectionSnapshot): StockCard
    {
        Gate::forUser($actor)->authorize('createDraft', StockCard::class);

        return DB::transaction(function () use ($actor, $reportDate, $flagUnit, $products, $categorySettingsSnapshot, $selectionSnapshot): StockCard {
            StockCard::query()->insertOrIgnore([
                'branch_id' => $actor->branch_id,
                'report_date' => $reportDate,
                'flag_unit' => $flagUnit,
                'status' => StockCardStatus::Draft->value,
                'category_settings_snapshot' => json_encode($categorySettingsSnapshot),
                'selection_snapshot' => json_encode($selectionSnapshot),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $card = StockCard::query()
                ->where('branch_id', $actor->branch_id)
                ->whereDate('report_date', $reportDate)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $card->status->canBeEditedBySubmitter() || $card->entries()->exists()) {
                return $card;
            }

            if ($card->selection_snapshot === null || $card->selection_snapshot === []) {
                $card->update(['selection_snapshot' => $selectionSnapshot]);
            }

            StockCardEntry::query()->upsert(
                array_map(fn (array $product): array => [
                    'stock_card_id' => $card->id,
                    'product_code' => $product['product_code'],
                    'product_name' => $product['product_name'],
                    'product_category' => $product['product_category'] ?? null,
                    'system_unit' => $product['system_unit'] ?? null,
                    'is_manual' => false,
                ], $products),
                ['stock_card_id', 'product_code'],
                ['product_name', 'product_category', 'system_unit'],
            );

            return $card;
        });
    }
}
