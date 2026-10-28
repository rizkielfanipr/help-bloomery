<?php

namespace App\Jobs\Kitchen;

use App\Actions\Kitchen\StoreProductionOrderHeadersAction;
use App\Enums\EsbProductionOrderErrorKind;
use App\Enums\EsbProductionOrderSyncStatus;
use App\Exceptions\Kitchen\EsbProductionOrderException;
use App\Models\EsbProductionOrderSyncRun;
use App\Services\EsbProductionOrderService;
use App\Services\Kitchen\ProductionOrderSyncPlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads the Production Order list of one run — one mapping and one window — page by page
 * (docs/products/kitchen/production-schedule/prd.md §30.2–§30.5). Each page is stored in its own small
 * transaction before the next request, and the details of new or changed orders are queued right
 * away, so a retry resumes after `last_page` without losing work. A job that used its time budget
 * hands the rest to a new job instead of running into the worker timeout.
 *
 * Retryable failures (timeout, 429, 5xx) are retried with backoff; authentication, validation,
 * configuration, and malformed responses fail the run without a retry. Earlier snapshots are never
 * removed. The payload holds the run ID only — never a token or a response.
 */
class SyncEsbProductionOrdersJob implements ShouldQueue
{
    use Queueable;

    /** Seconds of ESB work before the rest of the pages move to a follow-up job; below `$timeout`. */
    public const TIME_BUDGET_SECONDS = 45;

    public int $tries = 3;

    public int $timeout = 80;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $runId)
    {
        if (filled(config('kitchen.production_orders.queue'))) {
            $this->onQueue((string) config('kitchen.production_orders.queue'));
        }
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("kitchen-po-run:{$this->runId}"))->releaseAfter(60)->expireAfter(300)];
    }

    public function handle(EsbProductionOrderService $service, StoreProductionOrderHeadersAction $store, ProductionOrderSyncPlanner $planner): void
    {
        $run = EsbProductionOrderSyncRun::query()->with('mapping.branch')->find($this->runId);
        if ($run === null || ! $run->status->isActive()) {
            return;
        }

        $mapping = $run->mapping;
        $reason = $mapping === null ? 'Mapping ESB tidak ditemukan.' : $planner->skipReason($mapping);
        if ($reason !== null) {
            $run->markFailed(EsbProductionOrderErrorKind::Configuration->value, null, $reason);
            $this->log('warning', $run, 'Kitchen Production Order sync skipped.', ['error_class' => EsbProductionOrderErrorKind::Configuration->value]);

            return;
        }

        $run->update([
            'status' => EsbProductionOrderSyncStatus::Running,
            'attempts' => $run->attempts + 1,
            'started_at' => $run->started_at ?? now(),
            'correlation_id' => (string) Str::uuid(),
        ]);

        $startedAt = microtime(true);
        $limit = max(1, (int) config('kitchen.production_orders.page_limit', 100));
        $maxPages = max(1, (int) config('kitchen.production_orders.max_pages', 50));

        try {
            for ($page = $run->last_page + 1; ; $page++) {
                if ($page > $maxPages) {
                    throw EsbProductionOrderException::malformed("Daftar Production Order melewati batas {$maxPages} halaman.");
                }

                $result = $service->listPage($run->company_code, (int) $run->esb_branch_id, $run->date_from->toDateString(), $run->date_to->toDateString(), $page, $limit);
                $detailIds = $store->execute($run, $mapping, $result['orders']);

                foreach ($detailIds as $orderId) {
                    SyncEsbProductionOrderDetailJob::dispatch($orderId, $run->id);
                }

                $run->update([
                    'last_page' => $page,
                    'pages_fetched' => $run->pages_fetched + 1,
                    'details_expected' => $run->details_expected + count($detailIds),
                ]);
                $this->log('info', $run, 'Kitchen Production Order page stored.', ['page' => $page, 'headers' => count($result['orders'])]);

                if (! $result['has_next'] || $result['orders'] === [] || $page * $limit >= $result['count']) {
                    break;
                }

                if (microtime(true) - $startedAt >= self::TIME_BUDGET_SECONDS) {
                    self::dispatch($run->id)->delay(now()->addSeconds(5));

                    return;
                }
            }
        } catch (EsbProductionOrderException $exception) {
            $run->update(['error_class' => $exception->kind->value, 'error_code' => $exception->httpStatus === null ? null : (string) $exception->httpStatus, 'error_summary' => mb_substr($exception->getMessage(), 0, 500)]);
            $this->log('warning', $run, 'Kitchen Production Order list failed.', ['error_class' => $exception->kind->value, 'error_code' => $exception->httpStatus, 'attempt' => $this->attempts()]);

            if ($exception->isRetryable() && $this->attempts() < $this->tries) {
                throw $exception;
            }

            $run->markFailed($exception->kind->value, $exception->httpStatus === null ? null : (string) $exception->httpStatus, $exception->getMessage());

            return;
        }

        $run->update(['list_completed_at' => now(), 'error_class' => null, 'error_code' => null, 'error_summary' => null]);
        $run->finalizeIfComplete();
        $this->log('info', $run, 'Kitchen Production Order list completed.', [
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'pages_fetched' => $run->pages_fetched, 'headers_received' => $run->headers_received,
            'headers_created' => $run->headers_created, 'headers_updated' => $run->headers_updated,
            'headers_unchanged' => $run->headers_unchanged, 'conflicts' => $run->conflicts, 'details_expected' => $run->details_expected,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $run = EsbProductionOrderSyncRun::query()->find($this->runId);

        $run?->markFailed(
            $exception instanceof EsbProductionOrderException ? $exception->kind->value : 'unexpected',
            $exception instanceof EsbProductionOrderException && $exception->httpStatus !== null ? (string) $exception->httpStatus : null,
            $exception instanceof EsbProductionOrderException ? $exception->getMessage() : 'Sinkronisasi dihentikan karena error internal.',
        );
    }

    /** @param array<string, mixed> $context */
    private function log(string $level, EsbProductionOrderSyncRun $run, string $message, array $context = []): void
    {
        Log::log($level, $message, [
            'sync_run_id' => $run->id, 'mapping_id' => $run->branch_esb_code_id, 'branch_id' => $run->branch_id,
            'company_code' => $run->company_code, 'date_from' => $run->date_from?->toDateString(), 'date_to' => $run->date_to?->toDateString(),
            'correlation_id' => $run->correlation_id, ...$context,
        ]);
    }
}
