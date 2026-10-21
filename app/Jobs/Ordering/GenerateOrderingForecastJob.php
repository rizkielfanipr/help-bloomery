<?php

namespace App\Jobs\Ordering;

use App\Models\Branch;
use App\Services\Ordering\OrderingForecastGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Generates one forecast run per branch, cutoff, and horizon from the local snapshot
 * (docs/ordering-recommendation-prd.md §13). Reruns are idempotent; a failure leaves the latest
 * completed run in place.
 */
class GenerateOrderingForecastJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $timeout = 80;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [120];

    public function __construct(public int $branchId, public string $cutoff, public int $horizon)
    {
        if (filled(config('ordering_recommendation.queue'))) {
            $this->onQueue((string) config('ordering_recommendation.queue'));
        }
    }

    public function uniqueId(): string
    {
        return "ordering-forecast:{$this->branchId}:{$this->cutoff}:{$this->horizon}";
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->releaseAfter(60)->expireAfter(300)];
    }

    public function handle(OrderingForecastGenerator $generator): void
    {
        $branch = Branch::query()->find($this->branchId);
        if ($branch) {
            $generator->generate($branch, $this->cutoff, $this->horizon);
        }
    }
}
