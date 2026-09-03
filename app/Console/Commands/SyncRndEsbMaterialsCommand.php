<?php

namespace App\Console\Commands;

use App\Models\RndProductEsbMaterial;
use App\Services\EsbCoreService;
use App\Services\SyncRndEsbMaterialFromRemote;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rnd:sync-esb-materials')]
#[Description('Tarik perubahan Master Product ESB ke bahan RnD yang sudah tertaut')]
class SyncRndEsbMaterialsCommand extends Command
{
    public function handle(EsbCoreService $esb, SyncRndEsbMaterialFromRemote $sync): int
    {
        $remoteProducts = collect($esb->getAllProducts())->keyBy(
            fn (array $product): int => (int) ($product['productID'] ?? 0)
        );
        $synced = 0;
        $failed = 0;

        RndProductEsbMaterial::query()
            ->where('status', 'synced')
            ->whereNotNull('esb_product_id')
            ->chunkById(100, function ($materials) use ($remoteProducts, $sync, &$synced, &$failed): void {
                foreach ($materials as $material) {
                    try {
                        $sync->execute($material, $remoteProducts->get($material->esb_product_id));
                        $synced++;
                    } catch (\Throwable $exception) {
                        $material->update(['sync_error' => $exception->getMessage()]);
                        report($exception);
                        $failed++;
                    }
                }
            });

        $this->info("Sinkronisasi selesai: {$synced} berhasil, {$failed} gagal.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
