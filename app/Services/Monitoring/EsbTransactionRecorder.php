<?php

namespace App\Services\Monitoring;

use App\Enums\MonitoringTransactionStatus;
use App\Models\MonitoringTransaction;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records ESB mutation transactions (docs/system-monitoring-prd.md §11, §22).
 *
 * - Domain Actions/Pages open a parent with `transaction()`; domain services wrap each mutation
 *   request with `attempt()`. Attempts outside a parent get an implicit `system` parent.
 * - HTTP results are observed passively through the HTTP client events, so instrumentation never
 *   adds, repeats, or alters a request.
 * - Every write is guarded: a monitoring failure is logged and never fails or re-runs the domain
 *   mutation. Writes made inside a domain database transaction are deferred until the parent
 *   scope closes so a domain rollback cannot erase the evidence of an external call.
 */
class EsbTransactionRecorder
{
    /** @var list<EsbTransactionScope> */
    private array $scopes = [];

    private ?EsbAttempt $activeAttempt = null;

    /** @var array<int, array{scope: EsbTransactionScope, model: MonitoringTransaction}> */
    private array $deferred = [];

    public function __construct(
        private readonly MonitoringSanitizer $sanitizer,
        private readonly EsbOutcomeClassifier $classifier,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('monitoring.esb_transactions.enabled', true);
    }

    /**
     * Observe one user action that may perform ESB mutations.
     *
     * @template TResult
     *
     * @param  Closure(EsbTransactionScope): TResult  $callback
     * @param  array{company_code?: ?string, branch_id?: ?int, local_model?: ?Model, idempotency_key?: ?string, external_reference?: ?string, actor?: ?User}  $context
     * @return TResult
     */
    public function transaction(string $module, string $action, string $operation, Closure $callback, array $context = []): mixed
    {
        $scope = $this->open($this->newParent($module, $action, $operation, $context));

        return $this->runScope($scope, $callback);
    }

    /**
     * Continue an existing parent (retry of failed targets, attachment upload/delete) so new
     * attempts are appended and the old ones are kept (§11.6).
     *
     * @template TResult
     *
     * @param  Closure(EsbTransactionScope): TResult  $callback
     * @return TResult
     */
    public function resume(MonitoringTransaction $parent, Closure $callback): mixed
    {
        $scope = $this->open($parent);
        $scope->resumed = true;
        $scope->persisted = $parent->exists;
        $this->guard(function () use ($scope, $parent): void {
            $scope->attempts = $parent->attempts()->get()->all();
        });

        return $this->runScope($scope, $callback);
    }

    /**
     * Resume the latest parent recorded for a domain record, or start a new one.
     *
     * @template TResult
     *
     * @param  Closure(EsbTransactionScope): TResult  $callback
     * @param  array{company_code?: ?string, branch_id?: ?int, idempotency_key?: ?string, external_reference?: ?string, actor?: ?User}  $context
     * @return TResult
     */
    public function resumeOrStart(Model $localModel, string $module, string $action, string $operation, Closure $callback, array $context = []): mixed
    {
        $parent = $this->enabled() ? $this->latestParentFor($localModel) : null;

        return $parent
            ? $this->resume($parent, $callback)
            : $this->transaction($module, $action, $operation, $callback, $context + ['local_model' => $localModel]);
    }

    /**
     * Wrap exactly one ESB mutation request. `$send` performs the request itself; it is called
     * exactly once and its result/exception is returned/rethrown unchanged.
     *
     * @template TResult
     *
     * @param  Closure(EsbAttempt): TResult  $send
     * @return TResult
     */
    public function attempt(EsbMutation $mutation, Closure $send): mixed
    {
        if (! $this->enabled()) {
            return $send(new EsbAttempt(new MonitoringTransaction, $mutation));
        }

        $implicitScope = null;
        if ($this->scopes === []) {
            $implicitScope = $this->open($this->newParent('system', $mutation->operation, $mutation->operation, [
                'company_code' => $mutation->companyCode,
            ]));
        }

        $scope = $this->scopes[array_key_last($this->scopes)];
        $attempt = new EsbAttempt($this->newAttemptRecord($scope, $mutation), $mutation);
        $this->beginAttempt($scope, $attempt);

        $previous = $this->activeAttempt;
        $this->activeAttempt = $attempt;

        try {
            $result = $send($attempt);
        } catch (Throwable $exception) {
            $this->activeAttempt = $previous;
            $this->finishAttempt($scope, $attempt, $exception);
            if ($implicitScope) {
                $this->close($implicitScope, $exception);
            }

            throw $exception;
        }

        $this->activeAttempt = $previous;
        $this->finishAttempt($scope, $attempt, null);
        if ($implicitScope) {
            $this->close($implicitScope, null);
        }

        return $result;
    }

    public function currentScope(): ?EsbTransactionScope
    {
        return $this->scopes === [] ? null : $this->scopes[array_key_last($this->scopes)];
    }

    public function latestParentFor(Model $localModel): ?MonitoringTransaction
    {
        return $this->guard(fn (): ?MonitoringTransaction => MonitoringTransaction::query()
            ->parents()
            ->forCurrentEnvironment()
            ->forLocalModel($localModel)
            ->latest('id')
            ->first());
    }

    /**
     * Close an uncertain or partially failed parent after its real outcome was verified, either
     * by a domain reconciliation lookup or by a technical user's manual review. Attempts are kept.
     */
    public function reconcile(MonitoringTransaction $parent, string $outcome, ?string $note, ?User $actor): bool
    {
        if (! $parent->status->canTransitionTo(MonitoringTransactionStatus::Reconciled) || $parent->status === MonitoringTransactionStatus::Reconciled) {
            return false;
        }

        return (bool) $this->guard(function () use ($parent, $outcome, $note, $actor): bool {
            DB::transaction(function () use ($parent, $outcome, $note, $actor): void {
                $parent->attempts()
                    ->where('status', MonitoringTransactionStatus::Unknown->value)
                    ->update(['status' => MonitoringTransactionStatus::Reconciled->value, 'verified_at' => now()]);

                $parent->forceFill([
                    'status' => MonitoringTransactionStatus::Reconciled,
                    'reconciliation_outcome' => $outcome,
                    'reconciliation_note' => $this->sanitizer->message($note, 1000),
                    'reconciled_by' => $actor?->id,
                    'verified_at' => now(),
                ])->save();
            });

            return true;
        });
    }

    public function onRequestSending(RequestSending $event): void
    {
        if ($this->activeAttempt && $this->isMutationRequest($event->request)) {
            $this->activeAttempt->mutationSent = true;
        }
    }

    public function onResponseReceived(ResponseReceived $event): void
    {
        if ($this->activeAttempt && $this->isMutationRequest($event->request)) {
            $this->activeAttempt->lastResponse = $event->response;
        }
    }

    public function onConnectionFailed(ConnectionFailed $event): void
    {
        if ($this->activeAttempt && $this->isMutationRequest($event->request)) {
            $this->activeAttempt->connectionFailure = $event->exception->getMessage();
        }
    }

    private function isMutationRequest(Request $request): bool
    {
        return mb_strtoupper($request->method()) !== 'GET'
            && ! str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/auth/login');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function newParent(string $module, string $action, string $operation, array $context): MonitoringTransaction
    {
        $actor = $context['actor'] ?? Auth::user();
        $parent = new MonitoringTransaction([
            'uuid' => (string) Str::uuid(),
            'environment' => MonitoringContext::environment(),
            'source_module' => $module,
            'source_action' => $action,
            'operation_type' => $operation,
            'company_code' => $context['company_code'] ?? null,
            'branch_id' => $context['branch_id'] ?? null,
            'external_reference' => $context['external_reference'] ?? null,
            'correlation_id' => MonitoringContext::correlationId(),
            'idempotency_key' => isset($context['idempotency_key']) ? mb_substr((string) $context['idempotency_key'], 0, 120) : null,
            'status' => MonitoringTransactionStatus::Processing,
            'attempt_count' => 0,
            'actor_id' => $actor instanceof User ? $actor->id : null,
            'actor_role_snapshot' => $actor instanceof User ? $this->guard(fn (): string => mb_substr($actor->getRoleNames()->implode(', '), 0, 255)) : null,
            'deploy_commit' => MonitoringContext::deployCommit(),
            'started_at' => now(),
        ]);

        if (($context['local_model'] ?? null) instanceof Model) {
            $parent->local_model_type = $context['local_model']->getMorphClass();
            $parent->local_model_id = $context['local_model']->getKey();
        }

        return $parent;
    }

    private function newAttemptRecord(EsbTransactionScope $scope, EsbMutation $mutation): MonitoringTransaction
    {
        $targetKey = match (true) {
            $scope->targetPrefix !== null && $mutation->targetKey !== null => $scope->targetPrefix.':'.$mutation->targetKey,
            $scope->targetPrefix !== null => $scope->targetPrefix,
            default => $mutation->targetKey,
        };
        $previousForTarget = collect($scope->attempts)
            ->filter(fn (MonitoringTransaction $attempt): bool => $targetKey !== null && $attempt->target_key === $targetKey)
            ->count();

        return new MonitoringTransaction([
            'uuid' => (string) Str::uuid(),
            'environment' => $scope->parent->environment,
            'source_module' => $scope->parent->source_module,
            'source_action' => $scope->parent->source_action,
            'operation_type' => $mutation->operation,
            'target_key' => $targetKey !== null ? mb_substr($targetKey, 0, 120) : null,
            'company_code' => $mutation->companyCode ?? $scope->targetCompanyCode ?? $scope->parent->company_code,
            'branch_id' => $scope->parent->branch_id,
            'external_reference' => $mutation->externalReference,
            'correlation_id' => $scope->parent->correlation_id,
            'idempotency_key' => $scope->parent->idempotency_key,
            'method' => mb_strtoupper($mutation->method),
            'endpoint_template' => mb_substr($mutation->endpointTemplate, 0, 191),
            'status' => MonitoringTransactionStatus::Processing,
            'attempt_count' => $previousForTarget + 1,
            'request_summary' => $this->guard(fn (): array => $this->sanitizer->summarize($mutation->domain, $mutation->payload)) ?? [],
            'actor_id' => $scope->parent->actor_id,
            'deploy_commit' => $scope->parent->deploy_commit,
            'started_at' => now(),
        ]);
    }

    private function open(MonitoringTransaction $parent): EsbTransactionScope
    {
        $scope = new EsbTransactionScope($parent, DB::transactionLevel());
        $this->scopes[] = $scope;

        return $scope;
    }

    /**
     * @param  Closure(EsbTransactionScope): mixed  $callback
     */
    private function runScope(EsbTransactionScope $scope, Closure $callback): mixed
    {
        try {
            $result = $callback($scope);
        } catch (Throwable $exception) {
            $this->close($scope, $exception);

            throw $exception;
        }

        $this->close($scope, null);

        return $result;
    }

    private function beginAttempt(EsbTransactionScope $scope, EsbAttempt $attempt): void
    {
        if ($scope->resumed && $scope->parent->status !== MonitoringTransactionStatus::Processing) {
            $this->transitionParent($scope->parent, MonitoringTransactionStatus::Processing);
        }

        $scope->attempts[] = $attempt->record;
        $scope->parent->attempt_count = count($scope->attempts);
        $this->persist($scope, $scope->parent);
        $this->persist($scope, $attempt->record);
    }

    private function finishAttempt(EsbTransactionScope $scope, EsbAttempt $attempt, ?Throwable $exception): void
    {
        $this->guard(function () use ($attempt, $exception): void {
            [$status, $errorCode] = $this->classifier->classify($attempt, $exception);
            $response = $attempt->lastResponse;

            $attempt->record->fill([
                'status' => $status,
                'mutation_sent' => $attempt->mutationSent,
                'http_status' => $response?->status(),
                'response_summary' => $response ? $this->sanitizer->summarize('esb_response', $response->json()) : null,
                'error_class' => $exception ? mb_substr($exception::class, 0, 191) : null,
                'error_code' => $errorCode,
                'safe_error_message' => $exception ? $this->sanitizer->message($exception->getMessage()) : null,
                'completed_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $attempt->startedAt) * 1000),
            ]);

            if ($status === MonitoringTransactionStatus::Succeeded) {
                $attempt->record->verified_at = now();
            }
        });

        $this->persist($scope, $attempt->record);
    }

    private function close(EsbTransactionScope $scope, ?Throwable $exception): void
    {
        $index = array_search($scope, $this->scopes, true);
        if ($index !== false) {
            array_splice($this->scopes, $index, 1);
        }

        if (! $this->enabled() || $scope->attempts === []) {
            return;
        }

        $this->guard(function () use ($scope, $exception): void {
            $parent = $scope->parent;
            [$status, $message, $errorCode] = $this->deriveParentStatus($scope, $exception);
            $this->transitionParent($parent, $status);

            $attempts = collect($scope->attempts);
            $firstProblem = $attempts->first(fn (MonitoringTransaction $attempt): bool => $attempt->status !== MonitoringTransactionStatus::Succeeded);

            $parent->fill([
                'attempt_count' => $attempts->count(),
                'mutation_sent' => $attempts->contains(fn (MonitoringTransaction $attempt): bool => (bool) $attempt->mutation_sent),
                'http_status' => $attempts->last()?->http_status,
                'error_class' => $status === MonitoringTransactionStatus::Succeeded ? null : ($firstProblem?->error_class ?? ($exception ? mb_substr($exception::class, 0, 191) : null)),
                'error_code' => $status === MonitoringTransactionStatus::Succeeded ? null : $errorCode,
                'safe_error_message' => $status === MonitoringTransactionStatus::Succeeded ? null : ($message ?? $firstProblem?->safe_error_message),
                'completed_at' => now(),
                'duration_ms' => (int) $attempts->sum(fn (MonitoringTransaction $attempt): int => (int) $attempt->duration_ms),
                'verified_at' => $status === MonitoringTransactionStatus::Succeeded ? now() : $parent->verified_at,
            ]);

            if (blank($parent->external_reference)) {
                $parent->external_reference = $attempts->pluck('external_reference')->filter()->first();
            }
        });

        $this->flush($scope);
    }

    /**
     * Parent status is derived from the latest attempt per target so retried targets replace
     * their earlier failure, while targets that already succeeded keep their result.
     *
     * @return array{0: MonitoringTransactionStatus, 1: ?string, 2: ?string}
     */
    private function deriveParentStatus(EsbTransactionScope $scope, ?Throwable $exception): array
    {
        if ($scope->explicitStatus) {
            return [$scope->explicitStatus, $this->sanitizer->message($scope->explicitMessage), 'domain_unverified'];
        }

        $latest = collect($scope->attempts)
            ->groupBy(fn (MonitoringTransaction $attempt): string => $attempt->target_key ?? 'attempt:'.spl_object_id($attempt))
            ->map(fn ($attempts): MonitoringTransaction => $attempts->last());
        $statuses = $latest->map(fn (MonitoringTransaction $attempt): MonitoringTransactionStatus => $attempt->status)->values();
        $settled = fn (MonitoringTransactionStatus $status): bool => in_array($status, [MonitoringTransactionStatus::Succeeded, MonitoringTransactionStatus::Reconciled], true);

        if ($statuses->contains(MonitoringTransactionStatus::Unknown) || $statuses->contains(MonitoringTransactionStatus::Processing)) {
            return [MonitoringTransactionStatus::Unknown, null, $latest->first(fn ($attempt): bool => $attempt->status === MonitoringTransactionStatus::Unknown)?->error_code];
        }

        if ($statuses->every($settled)) {
            return $exception
                ? [MonitoringTransactionStatus::Unknown, 'Mutation diterima ESB, tetapi langkah lanjutan gagal: '.$this->sanitizer->message($exception->getMessage(), 300), 'post_mutation_failure']
                : [MonitoringTransactionStatus::Succeeded, null, null];
        }

        if ($statuses->contains($settled)) {
            return [MonitoringTransactionStatus::Partial, null, 'partial_targets'];
        }

        return [MonitoringTransactionStatus::Failed, null, $latest->last()?->error_code];
    }

    private function transitionParent(MonitoringTransaction $parent, MonitoringTransactionStatus $next): void
    {
        $current = $parent->status ?? MonitoringTransactionStatus::Processing;
        if (! $current->canTransitionTo($next)) {
            Log::warning('Monitoring transaction status transition refused.', ['uuid' => $parent->uuid, 'from' => $current->value, 'to' => $next->value]);

            return;
        }

        $parent->status = $next;
    }

    private function persist(EsbTransactionScope $scope, MonitoringTransaction $model): void
    {
        if (DB::transactionLevel() > $scope->baseTransactionLevel) {
            $this->deferred[spl_object_id($model)] = ['scope' => $scope, 'model' => $model];

            return;
        }

        $this->save($scope, $model);
    }

    private function save(EsbTransactionScope $scope, MonitoringTransaction $model): void
    {
        $this->guard(function () use ($scope, $model): void {
            if ($model !== $scope->parent) {
                if (! $scope->parent->exists) {
                    $scope->parent->save();
                }

                $model->parent_id = $scope->parent->id;
            }

            $model->save();
            $scope->persisted = true;
        });
    }

    private function flush(EsbTransactionScope $scope): void
    {
        foreach ($this->deferred as $key => $entry) {
            if ($entry['scope'] === $scope) {
                unset($this->deferred[$key]);
            }
        }

        $this->save($scope, $scope->parent);
        foreach ($scope->attempts as $attempt) {
            if (! $attempt->exists || $attempt->isDirty()) {
                $this->save($scope, $attempt);
            }
        }
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue|null
     */
    private function guard(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            Log::warning('System monitoring recorder failed; the business operation continues unchanged.', [
                'exception' => $exception::class,
                'message' => $this->sanitizer->message($exception->getMessage()),
            ]);

            return null;
        }
    }
}
