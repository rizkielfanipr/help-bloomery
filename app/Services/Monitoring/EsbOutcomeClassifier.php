<?php

namespace App\Services\Monitoring;

use App\Enums\MonitoringTransactionStatus;
use Throwable;

/**
 * Maps an observed mutation attempt to a monitoring status (docs/operations/monitoring/prd.md §11.2).
 *
 * A failure is only `failed` when there is evidence ESB did not apply the mutation: it was never
 * sent, the connection was refused before delivery, or ESB answered with an explicit rejection.
 * Timeouts, connection resets, 5xx, and malformed or unverifiable responses after the request was
 * sent are `unknown` and must be reconciled, never retried automatically.
 */
class EsbOutcomeClassifier
{
    /**
     * @return array{0: MonitoringTransactionStatus, 1: ?string}
     */
    public function classify(EsbAttempt $attempt, ?Throwable $exception): array
    {
        if ($exception === null) {
            return [MonitoringTransactionStatus::Succeeded, null];
        }

        if (! $attempt->mutationSent) {
            return [MonitoringTransactionStatus::Failed, 'not_sent'];
        }

        $response = $attempt->lastResponse;
        if ($response !== null) {
            $status = $response->status();
            if ($status >= 500) {
                return [MonitoringTransactionStatus::Unknown, 'http_5xx'];
            }

            if ($status >= 400) {
                return [MonitoringTransactionStatus::Failed, 'http_'.$status];
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return [MonitoringTransactionStatus::Unknown, 'malformed_response'];
            }

            $declaredStatus = $payload['status'] ?? null;
            $declaredCode = (int) ($payload['code'] ?? 0);
            if ((is_string($declaredStatus) && $declaredStatus !== 'ok') || $declaredCode >= 400) {
                return [MonitoringTransactionStatus::Failed, 'esb_rejected'];
            }

            return [MonitoringTransactionStatus::Unknown, 'unverified_response'];
        }

        if ($attempt->connectionFailure !== null) {
            return preg_match('/cURL error (6|7)\b/', $attempt->connectionFailure) === 1
                ? [MonitoringTransactionStatus::Failed, 'connection_refused']
                : [MonitoringTransactionStatus::Unknown, 'connection_lost'];
        }

        return [MonitoringTransactionStatus::Unknown, 'unexpected_after_send'];
    }
}
