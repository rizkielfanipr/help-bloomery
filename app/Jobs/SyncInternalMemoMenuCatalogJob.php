<?php

namespace App\Jobs;

use App\Models\RndInternalMemoBranch;
use App\Models\RndInternalMemoMenuCatalog;
use App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class SyncInternalMemoMenuCatalogJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(public string $companyCode, public string $branchCode) {}

    public function uniqueId(): string
    {
        return mb_strtoupper($this->companyCode).'|'.mb_strtoupper($this->branchCode);
    }

    /**
     * Execute the job.
     */
    public function handle(InternalMemoMenuCatalogService $catalog): void
    {
        $companyCode = mb_strtoupper(trim($this->companyCode));
        $branchCode = mb_strtoupper(trim($this->branchCode));
        $startedAt = hrtime(true);

        $this->contextBranches($companyCode, $branchCode)->update([
            'catalog_sync_status' => 'syncing',
            'catalog_sync_error' => null,
        ]);

        try {
            $menus = $catalog->allForContext($companyCode, $branchCode);
            $syncedAt = now();

            DB::transaction(function () use ($menus, $companyCode, $branchCode, $syncedAt): void {
                $menuIds = [];

                foreach ($menus as $menu) {
                    $menuId = (int) $menu['menuID'];
                    $menuIds[] = $menuId;
                    RndInternalMemoMenuCatalog::query()->updateOrCreate(
                        [
                            'company_code' => $companyCode,
                            'branch_code' => $branchCode,
                            'menu_id' => $menuId,
                        ],
                        [
                            'company_code' => $companyCode,
                            'branch_code' => $branchCode,
                            'menu_code' => $menu['menuCode'] ?: null,
                            'menu_name' => $menu['menuName'],
                            'bom_id' => $menu['bomID'],
                            'bom_name' => $menu['bomName'],
                            'category_detail' => $menu['categoryDetail'],
                            'flag_active' => $menu['flagActive'],
                            'raw_snapshot' => $menu['raw'],
                            'synced_at' => $syncedAt,
                        ],
                    );
                }

                $stale = RndInternalMemoMenuCatalog::query()
                    ->where('company_code', $companyCode)
                    ->where('branch_code', $branchCode);

                if ($menuIds === []) {
                    $stale->delete();
                } else {
                    $stale->whereNotIn('menu_id', $menuIds)->delete();
                }

                $this->contextBranches($companyCode, $branchCode)->update([
                    'catalog_sync_status' => 'synced',
                    'catalog_synced_at' => $syncedAt,
                    'catalog_sync_error' => null,
                ]);
            });

            Log::info('Internal Memo menu catalog synchronized.', [
                'company_code' => $companyCode,
                'branch_code' => $branchCode,
                'menu_count' => count($menus),
                'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
                'queue_job_id' => $this->job?->getJobId(),
            ]);
        } catch (Throwable $exception) {
            $this->recordFailure($companyCode, $branchCode, $exception);
            Log::warning('Internal Memo menu catalog synchronization failed.', [
                'company_code' => $companyCode,
                'branch_code' => $branchCode,
                'exception' => $exception::class,
                'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
                'queue_job_id' => $this->job?->getJobId(),
            ]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->recordFailure(
            mb_strtoupper(trim($this->companyCode)),
            mb_strtoupper(trim($this->branchCode)),
            $exception ?? new \RuntimeException('Sinkronisasi katalog dihentikan tanpa detail error.'),
        );
    }

    /** @return Builder<RndInternalMemoBranch> */
    private function contextBranches(string $companyCode, string $branchCode): Builder
    {
        return RndInternalMemoBranch::query()
            ->where('company_code_snapshot', $companyCode)
            ->where('branch_code_snapshot', $branchCode);
    }

    private function recordFailure(string $companyCode, string $branchCode, Throwable $exception): void
    {
        $this->contextBranches($companyCode, $branchCode)->update([
            'catalog_sync_status' => 'failed',
            'catalog_sync_error' => mb_substr($exception->getMessage(), 0, 1000),
        ]);
    }
}
