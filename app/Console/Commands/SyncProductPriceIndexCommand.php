<?php

namespace App\Console\Commands;

use App\Models\ProductPriceSyncRun;
use App\Services\ProductPriceSnapshotService;
use App\Services\PurchaseOrderPriceSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

#[Signature('product-price-index:sync {--days=90 : Rolling purchase period in days}')]
#[Description('Synchronize ESB purchase orders and store a local weekly price snapshot')]
class SyncProductPriceIndexCommand extends Command
{
    public function handle(PurchaseOrderPriceSyncService $sync, ProductPriceSnapshotService $snapshots): int
    {
        $days = max(1, (int) $this->option('days'));
        $periodEnd = today();
        $periodStart = $periodEnd->copy()->subDays($days - 1);
        $run = ProductPriceSyncRun::query()->create([
            'status' => 'running',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'started_at' => now(),
        ]);

        try {
            $result = $sync->sync($periodStart->toDateString(), $periodEnd->toDateString(), 100, 1000);
            $products = $snapshots->create(Carbon::today(), $periodStart, $periodEnd);
            $run->update([
                'status' => $result['failed'] > 0 ? 'partial' : 'completed',
                'orders_synced' => $result['orders'],
                'items_synced' => $result['items'],
                'products_snapshotted' => $products,
                'failed_orders' => $result['failed'],
                'errors' => $result['errors'],
                'finished_at' => now(),
            ]);
            $this->info("Product Price Index selesai: {$products} produk disimpan.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'errors' => [$exception->getMessage()],
                'finished_at' => now(),
            ]);
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
