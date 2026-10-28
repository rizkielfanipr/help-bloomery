<?php

namespace App\Jobs\Kitchen;

use App\Actions\Kitchen\StoreProductionOrderDetailAction;
use App\Exceptions\Kitchen\EsbProductionOrderException;
use App\Models\EsbProductionOrder;
use App\Models\EsbProductionOrderSyncRun;
use App\Services\EsbProductionOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads and stores the details of one Production Order snapshot (docs/products/kitchen/production-schedule/prd.md
 * §30.4). The ESB context comes from the snapshot, never from a browser. Each terminal outcome —
 * stored, permanent failure, or retries exhausted — is reported to the run exactly once, and a
 * failed order turns the run `partial` while every other snapshot stays valid.
 *
 * Duplicate jobs for the same order are serialized by `WithoutOverlapping` (unique-job locks would
 * drop a dispatch and leave the run waiting for an outcome that never comes).
 */
class SyncEsbProductionOrderDetailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 80;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $productionOrderId, public ?int $runId = null)
    {
        if (filled(config('kitchen.production_orders.queue'))) {
            $this->onQueue((string) config('kitchen.production_orders.queue'));
        }
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("kitchen-po-detail:{$this->productionOrderId}"))->releaseAfter(30)->expireAfter(180)];
    }

    public function handle(EsbProductionOrderService $service, StoreProductionOrderDetailAction $store): void
    {
        $order = EsbProductionOrder::query()->find($this->productionOrderId);
        if ($order === null) {
            $this->reportOutcome(false);

            return;
        }

        try {
            $payload = $service->order($order->company_code, $order->esb_branch_id, $order->production_order_num);
        } catch (EsbProductionOrderException $exception) {
            Log::warning('Kitchen Production Order detail failed.', [
                'sync_run_id' => $this->runId, 'production_order_id' => $order->id, 'company_code' => $order->company_code,
                'branch_id' => $order->branch_id, 'error_class' => $exception->kind->value, 'error_code' => $exception->httpStatus, 'attempt' => $this->attempts(),
            ]);

            if ($exception->isRetryable() && $this->attempts() < $this->tries) {
                throw $exception;
            }

            $this->reportOutcome(false);

            return;
        }

        $store->execute($order, $payload);
        $this->reportOutcome(true);
    }

    public function failed(?Throwable $exception): void
    {
        $this->reportOutcome(false);
    }

    private function reportOutcome(bool $succeeded): void
    {
        if ($this->runId !== null) {
            EsbProductionOrderSyncRun::query()->find($this->runId)?->recordDetailOutcome($succeeded);
        }
    }
}
