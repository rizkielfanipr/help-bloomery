<?php

namespace App\Services;

use App\Models\StockCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockCardEsbSynchronizer
{
    public function __construct(private EsbStockMovementService $movements) {}

    public function canRefresh(StockCard $card): bool
    {
        $user = auth()->user();

        return $user?->can('refreshEsb', $card) ?? false;
    }

    /** @return array<string, mixed> */
    public function refresh(StockCard $card): array
    {
        abort_unless($this->canRefresh($card), 403);
        $result = $this->movements->balancesForBranch($card->branch, $card->report_date->toDateString(), $card->flag_unit);
        $balances = collect($result['rows'])->keyBy('productCode');
        if ($balances->isEmpty()) {
            throw ValidationException::withMessages(['esb' => 'Belum ada data ESB untuk tanggal ini']);
        }
        DB::transaction(function () use ($card, $balances, $result): void {
            $locked = StockCard::query()->lockForUpdate()->findOrFail($card->id);
            abort_unless($this->canRefresh($locked), 403);
            foreach ($locked->entries as $entry) {
                $balance = $balances->get($entry->product_code);
                if (! $balance || $balance['unit'] !== $entry->system_unit) {
                    throw ValidationException::withMessages(['esb' => 'Saldo sistem belum lengkap: '.$entry->product_code]);
                }
                $entry->update(['system_qty' => (float) $balance['totalQty']]);
            }
            $locked->update(['system_fetched_at' => now(), 'movement_snapshot' => $result]);
        });

        return $result;
    }
}
