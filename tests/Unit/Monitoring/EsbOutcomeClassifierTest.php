<?php

use App\Enums\MonitoringTransactionStatus;
use App\Models\MonitoringTransaction;
use App\Services\Monitoring\EsbAttempt;
use App\Services\Monitoring\EsbMutation;
use App\Services\Monitoring\EsbOutcomeClassifier;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;

function classifierAttempt(bool $sent = true, ?Response $response = null, ?string $connectionFailure = null): EsbAttempt
{
    $attempt = new EsbAttempt(new MonitoringTransaction, new EsbMutation('POST', '/product', 'create_product', 'master_product'));
    $attempt->mutationSent = $sent;
    $attempt->lastResponse = $response;
    $attempt->connectionFailure = $connectionFailure;

    return $attempt;
}

function classifierResponse(int $status, string $body): Response
{
    return new Response(new PsrResponse($status, ['Content-Type' => 'application/json'], $body));
}

it('maps outcomes conservatively', function (EsbAttempt $attempt, ?Throwable $exception, MonitoringTransactionStatus $status, ?string $code) {
    expect((new EsbOutcomeClassifier)->classify($attempt, $exception))->toBe([$status, $code]);
})->with([
    'success' => [fn () => classifierAttempt(response: classifierResponse(200, '{"status":"ok"}')), null, MonitoringTransactionStatus::Succeeded, null],
    'never sent (credential missing)' => [fn () => classifierAttempt(sent: false), new RuntimeException('Credential belum dikonfigurasi'), MonitoringTransactionStatus::Failed, 'not_sent'],
    'explicit 422 rejection' => [fn () => classifierAttempt(response: classifierResponse(422, '{"status":"fail"}')), new RuntimeException('x'), MonitoringTransactionStatus::Failed, 'http_422'],
    'esb rejection body on 200' => [fn () => classifierAttempt(response: classifierResponse(200, '{"status":"fail","errors":[{"message":"duplicate"}]}')), new RuntimeException('x'), MonitoringTransactionStatus::Failed, 'esb_rejected'],
    'promotion code >= 400 on 200' => [fn () => classifierAttempt(response: classifierResponse(200, '{"code":422,"message":"invalid"}')), new RuntimeException('x'), MonitoringTransactionStatus::Failed, 'esb_rejected'],
    '5xx gateway error' => [fn () => classifierAttempt(response: classifierResponse(504, 'Gateway Timeout')), new RuntimeException('x'), MonitoringTransactionStatus::Unknown, 'http_5xx'],
    'malformed 200 body' => [fn () => classifierAttempt(response: classifierResponse(200, '<html>')), new RuntimeException('x'), MonitoringTransactionStatus::Unknown, 'malformed_response'],
    'ok body without the expected reference' => [fn () => classifierAttempt(response: classifierResponse(200, '{"status":"ok","result":{}}')), new RuntimeException('ESB tidak mengembalikan Product ID.'), MonitoringTransactionStatus::Unknown, 'unverified_response'],
    'timeout after send' => [fn () => classifierAttempt(connectionFailure: 'cURL error 28: Operation timed out'), new RuntimeException('x'), MonitoringTransactionStatus::Unknown, 'connection_lost'],
    'connection reset after send' => [fn () => classifierAttempt(connectionFailure: 'connection reset after send'), new RuntimeException('x'), MonitoringTransactionStatus::Unknown, 'connection_lost'],
    'connection refused before delivery' => [fn () => classifierAttempt(connectionFailure: 'cURL error 7: Failed to connect'), new RuntimeException('x'), MonitoringTransactionStatus::Failed, 'connection_refused'],
]);

it('never allows unknown to be marked retryable automatically', function () {
    expect(MonitoringTransactionStatus::Unknown->needsReconciliation())->toBeTrue()
        ->and(MonitoringTransactionStatus::Unknown->canTransitionTo(MonitoringTransactionStatus::Succeeded))->toBeFalse()
        ->and(MonitoringTransactionStatus::Unknown->canTransitionTo(MonitoringTransactionStatus::Reconciled))->toBeTrue()
        ->and(MonitoringTransactionStatus::Cancelled->canTransitionTo(MonitoringTransactionStatus::Processing))->toBeFalse()
        ->and(MonitoringTransactionStatus::Succeeded->canTransitionTo(MonitoringTransactionStatus::Succeeded))->toBeTrue();
});
