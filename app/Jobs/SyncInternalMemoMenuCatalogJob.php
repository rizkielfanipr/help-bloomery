<?php

namespace App\Jobs;

use App\Exceptions\Rnd\InternalMemoCatalogUnavailableException;
use App\Models\RndInternalMemoCatalogSync;
use App\Models\RndInternalMemoMenuCatalog;
use App\Services\Rnd\InternalMemo\InternalMemoCatalogContext;
use App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Refreshes the one global Memo Internal Master Menu snapshot (docs/rnd-internal-memo-brand-prd.md
 * §11.4, §14.4): Company Code BLSS and a branch discovered from ESB's BLSS branch list
 * (InternalMemoCatalogContext::candidateBranchCodes) — never a Brand or a Memo Branch. Upserts are idempotent, rows ESB no longer returns are removed only after a complete
 * non-empty fetch, and any failure keeps the last-known-good snapshot.
 *
 * `$timeout` stays below the queue `retry_after` (90s) so a slow run is never handed to a second
 * worker; WithoutOverlapping guards execution and ShouldBeUnique guards dispatch.
 */
class SyncInternalMemoMenuCatalogJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const UPSERT_CHUNK = 500;

    public int $tries = 3;

    public int $timeout = 80;

    public int $uniqueFor = 600;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public ?int $triggeredBy = null) {}

    public function uniqueId(): string
    {
        return 'rnd-internal-memo-catalog';
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('rnd-internal-memo-catalog'))->releaseAfter(60)->expireAfter(300)];
    }

    public function handle(InternalMemoMenuCatalogService $catalog, InternalMemoCatalogContext $context): void
    {
        $correlationId = (string) Str::uuid();
        $startedAt = hrtime(true);
        $companyCode = $context->companyCode();

        $branchCode = null;
        $state = RndInternalMemoCatalogSync::query()->firstOrCreate(['company_code' => $companyCode]);
        $state->update([
            'status' => RndInternalMemoCatalogSync::STATUS_RUNNING,
            'last_started_at' => now(),
            'triggered_by' => $this->triggeredBy ?? $state->triggered_by,
        ]);

        try {
            [$branchCode, $menus] = $this->fetchFirstAvailableCatalog($catalog, $context);

            // Whole seconds: stale rows are told apart by `synced_at < $syncedAt` after the upsert.
            $syncedAt = now()->startOfSecond();
            DB::transaction(function () use ($menus, $companyCode, $branchCode, $syncedAt): void {
                foreach (array_chunk($menus, self::UPSERT_CHUNK) as $chunk) {
                    RndInternalMemoMenuCatalog::query()->upsert(
                        array_map(fn (array $menu): array => [
                            'company_code' => $companyCode,
                            'branch_code' => $branchCode,
                            'menu_id' => (int) $menu['menuID'],
                            'menu_code' => $menu['menuCode'] ?: null,
                            'menu_name' => $menu['menuName'],
                            'bom_id' => $menu['bomID'],
                            'bom_name' => $menu['bomName'],
                            'category_detail' => $menu['categoryDetail'],
                            'flag_active' => $menu['flagActive'],
                            'raw_snapshot' => json_encode($menu['raw'], JSON_THROW_ON_ERROR),
                            'synced_at' => $syncedAt,
                            'created_at' => $syncedAt,
                            'updated_at' => $syncedAt,
                        ], $chunk),
                        ['company_code', 'branch_code', 'menu_id'],
                        ['menu_code', 'menu_name', 'bom_id', 'bom_name', 'category_detail', 'flag_active', 'raw_snapshot', 'synced_at', 'updated_at'],
                    );
                }

                RndInternalMemoMenuCatalog::query()
                    ->where('company_code', $companyCode)
                    ->where('branch_code', $branchCode)
                    ->where('synced_at', '<', $syncedAt)
                    ->delete();
            });

            $state->update([
                'status' => RndInternalMemoCatalogSync::STATUS_SUCCESS,
                'technical_branch_code' => $branchCode,
                'menu_count' => count($menus),
                'last_synced_at' => $syncedAt,
                'last_error' => null,
            ]);

            Log::info('Internal Memo menu catalog synchronized.', [
                'company_code' => $companyCode,
                'technical_branch_code' => $branchCode,
                'status' => RndInternalMemoCatalogSync::STATUS_SUCCESS,
                'menu_count' => count($menus),
                'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
                'correlation_id' => $correlationId,
                'queue_job_id' => $this->job?->getJobId(),
            ]);
        } catch (Throwable $exception) {
            $this->recordFailure($state, $exception);
            Log::warning('Internal Memo menu catalog synchronization failed.', [
                'company_code' => $companyCode,
                'technical_branch_code' => $branchCode,
                'status' => RndInternalMemoCatalogSync::STATUS_FAILED,
                'exception' => $exception::class,
                'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
                'correlation_id' => $correlationId,
                'queue_job_id' => $this->job?->getJobId(),
            ]);

            throw $exception;
        }
    }

    /**
     * Tries the BLSS branches in their fixed order and returns the first non-empty catalog. An empty
     * or failed answer for one branch only moves on to the next; if all fail, the last error is
     * raised and the previous snapshot stays.
     *
     * @return array{0: string, 1: list<array<string, mixed>>}
     */
    private function fetchFirstAvailableCatalog(InternalMemoMenuCatalogService $catalog, InternalMemoCatalogContext $context): array
    {
        $lastError = null;

        foreach ($context->candidateBranchCodes() as $branchCode) {
            try {
                $menus = $catalog->allForContext($context->companyCode(), $branchCode);
            } catch (Throwable $exception) {
                $lastError = $exception;

                continue;
            }

            if ($menus !== []) {
                return [$branchCode, $menus];
            }
        }

        throw $lastError ?? new InternalMemoCatalogUnavailableException('ESB Master Menu BLSS tidak mengembalikan Menu aktif; snapshot sebelumnya dipertahankan.');
    }

    public function failed(?Throwable $exception): void
    {
        $state = app(InternalMemoCatalogContext::class)->state();

        if ($state !== null) {
            $this->recordFailure($state, $exception ?? new RuntimeException('Sinkronisasi katalog dihentikan tanpa detail error.'));
        }
    }

    private function recordFailure(RndInternalMemoCatalogSync $state, Throwable $exception): void
    {
        $state->update([
            'status' => RndInternalMemoCatalogSync::STATUS_FAILED,
            'last_failed_at' => now(),
            'last_error' => mb_substr($exception->getMessage(), 0, 1000),
        ]);
    }
}
