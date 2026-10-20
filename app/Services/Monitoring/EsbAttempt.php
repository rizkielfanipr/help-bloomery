<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringTransaction;
use Illuminate\Http\Client\Response;

/**
 * Handle for one in-flight mutation attempt. HTTP observations are fed by the recorder's HTTP
 * client event listeners; the domain service may add the external reference it received.
 */
final class EsbAttempt
{
    public bool $mutationSent = false;

    public ?Response $lastResponse = null;

    public ?string $connectionFailure = null;

    public float $startedAt;

    public function __construct(public readonly MonitoringTransaction $record, public readonly EsbMutation $mutation)
    {
        $this->startedAt = microtime(true);
    }

    public function reference(string|int|null $externalReference): void
    {
        if ($externalReference !== null && (string) $externalReference !== '') {
            $this->record->external_reference = mb_substr((string) $externalReference, 0, 120);
        }
    }
}
