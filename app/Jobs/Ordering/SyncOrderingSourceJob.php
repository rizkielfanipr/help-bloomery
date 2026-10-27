<?php

namespace App\Jobs\Ordering;

use App\Enums\OrderingSource;
use App\Enums\OrderingSyncStatus;
use App\Models\BranchEsbCode;
use App\Models\OrderingSyncRun;
use App\Services\Ordering\OrderingDailyMetricBuilder;
use App\Services\Ordering\OrderingDailyUsageSource;
use App\Services\Ordering\OrderingStockMovementSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Snapshots one source for one company pair and one day (docs/products/analytics/ordering-recommendation/prd.md §12,
 * §21). The ESB read happens before any transaction; only a complete read is written. A run that
 * already completed is skipped (resume), a retryable failure (timeout, 429, 5xx) is retried with
 * backoff, and a validation or mapping failure is recorded without a retry. A failure never
 * replaces earlier good metrics. The payload holds IDs only — never a token.
 *
 * `$timeout` stays below the queue `retry_after` (90s); ShouldBeUnique guards dispatch and
 * WithoutOverlapping guards execution per source, pair, and date.
 */
class SyncOrderingSourceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 80;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public string $source, public int $pairId, public string $date, public bool $force = false)
    {
        if (filled(config('ordering_recommendation.queue'))) {
            $this->onQueue((string) config('ordering_recommendation.queue'));
        }
    }

    public function uniqueId(): string
    {
        return "ordering:{$this->source}:{$this->pairId}:{$this->date}";
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->releaseAfter(60)->expireAfter(300)];
    }

    public function handle(OrderingDailyUsageSource $usage, OrderingStockMovementSource $stock, OrderingDailyMetricBuilder $builder): void
    {
        $source = OrderingSource::from($this->source);
        $pair = BranchEsbCode::query()->find($this->pairId);
        if (! $pair) {
            return;
        }

        $run = $this->run($source, $pair);
        if ($run->status === OrderingSyncStatus::Completed && ! $this->force) {
            return;
        }

        $run->update([
            'status' => OrderingSyncStatus::Running, 'attempts' => $run->attempts + 1, 'started_at' => now(),
            'finished_at' => null, 'error_summary' => null, 'correlation_id' => (string) Str::uuid(),
        ]);

        $flagUnit = (string) config('ordering_recommendation.flag_unit');
        $result = $source === OrderingSource::DailyUsage
            ? $usage->fetch($pair, $this->date, $flagUnit)
            : $stock->fetch($pair, $this->date, $flagUnit);

        if ($result->status !== OrderingSyncStatus::Completed) {
            $run->update(['status' => OrderingSyncStatus::Failed, 'finished_at' => now(), 'error_summary' => $result->error]);
            Log::warning('Ordering sync failed.', ['source' => $source->value, 'pair' => $pair->id, 'date' => $this->date, 'retryable' => $result->retryable, 'correlation_id' => $run->correlation_id]);
            if ($result->retryable) {
                throw new RuntimeException((string) $result->error);
            }

            return;
        }

        $stored = DB::transaction(fn (): int => $source === OrderingSource::DailyUsage
            ? $builder->applyUsage($run, $pair, $this->date, $result->rows)
            : $builder->applyStock($run, $pair, $this->date, $result->rows));

        $run->update([
            'status' => OrderingSyncStatus::Completed, 'finished_at' => now(), 'rows_received' => count($result->rows),
            'rows_stored' => $stored, 'pages_fetched' => $result->pages,
            'metadata' => ['flag_unit' => $flagUnit, 'duration_ms' => (int) $run->started_at?->diffInMilliseconds(now())],
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $pair = BranchEsbCode::query()->find($this->pairId);
        if (! $pair) {
            return;
        }

        $this->run(OrderingSource::from($this->source), $pair)->update([
            'status' => OrderingSyncStatus::Failed,
            'finished_at' => now(),
            'error_summary' => mb_substr($exception?->getMessage() ?? 'Sinkronisasi dihentikan tanpa detail error.', 0, 500),
        ]);
    }

    private function run(OrderingSource $source, BranchEsbCode $pair): OrderingSyncRun
    {
        return OrderingSyncRun::query()->firstOrCreate(
            ['idempotency_key' => OrderingSyncRun::idempotencyKey($source, $pair, $this->date)],
            [
                'source' => $source, 'branch_id' => $pair->branch_id, 'company_code' => (string) $pair->esb_comcode,
                'esb_branch_code' => (string) $pair->esb_branch_code, 'period_from' => $this->date, 'period_to' => $this->date,
                'status' => OrderingSyncStatus::Pending,
            ],
        );
    }
}
