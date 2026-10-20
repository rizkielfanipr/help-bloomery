<?php

namespace App\Services\Monitoring;

/**
 * Describes one ESB mutation request before it is sent. `payload` is only used to build the
 * sanitized allowlist summary; the raw payload is never persisted.
 */
final readonly class EsbMutation
{
    /**
     * @param  string  $endpointTemplate  path template without identifiers, e.g. `/product/{productID}`
     * @param  string  $domain  sanitizer allowlist key (see MonitoringSanitizer::ALLOWLISTS)
     * @param  string|null  $targetKey  stable key of the mutated target (comcode, journal step) used to derive partial results
     */
    public function __construct(
        public string $method,
        public string $endpointTemplate,
        public string $operation,
        public string $domain,
        public mixed $payload = null,
        public ?string $companyCode = null,
        public ?string $targetKey = null,
        public ?string $externalReference = null,
    ) {}
}
