<?php

namespace App\Services\Monitoring;

use App\Enums\MonitoringTransactionStatus;
use App\Models\MonitoringTransaction;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * One user action observed by the recorder. The parent record is only persisted once the first
 * mutation attempt starts, so actions that never reach ESB (validation errors, zero accepted
 * quantity, existing product linked) leave no monitoring row.
 */
final class EsbTransactionScope
{
    /** @var list<MonitoringTransaction> */
    public array $attempts = [];

    public ?MonitoringTransactionStatus $explicitStatus = null;

    public ?string $explicitMessage = null;

    public bool $persisted = false;

    public bool $resumed = false;

    public ?string $targetPrefix = null;

    public ?string $targetCompanyCode = null;

    public float $startedAt;

    public function __construct(
        public readonly MonitoringTransaction $parent,
        public readonly int $baseTransactionLevel,
    ) {
        $this->startedAt = microtime(true);
    }

    public function linkLocal(Model $model): self
    {
        $this->parent->local_model_type = $model->getMorphClass();
        $this->parent->local_model_id = $model->getKey();

        return $this;
    }

    public function reference(string|int|null $externalReference): self
    {
        if ($externalReference !== null && (string) $externalReference !== '') {
            $this->parent->external_reference = mb_substr((string) $externalReference, 0, 120);
        }

        return $this;
    }

    /**
     * The mutation was accepted but the domain could not verify the final state.
     */
    public function markUnknown(?string $reason = null): self
    {
        $this->explicitStatus = MonitoringTransactionStatus::Unknown;
        $this->explicitMessage = $reason;

        return $this;
    }

    /**
     * Attribute every attempt made inside `$callback` to one target (e.g. one comcode of a
     * multi-company submission) so partial success is derived per target.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function forTarget(string $target, ?string $companyCode, Closure $callback): mixed
    {
        [$previousPrefix, $previousCompany] = [$this->targetPrefix, $this->targetCompanyCode];
        [$this->targetPrefix, $this->targetCompanyCode] = [$target, $companyCode];

        try {
            return $callback();
        } finally {
            [$this->targetPrefix, $this->targetCompanyCode] = [$previousPrefix, $previousCompany];
        }
    }

    /**
     * Append a non-mutation step (verification read, reconciliation lookup) to the timeline.
     */
    public function timeline(string $step, string $result): self
    {
        $timeline = $this->parent->timeline ?? [];
        $timeline[] = ['step' => mb_substr($step, 0, 60), 'result' => mb_substr($result, 0, 120), 'at' => now()->toIso8601String()];
        $this->parent->timeline = array_slice($timeline, -20);

        return $this;
    }
}
