<?php

namespace App\Services\Monitoring;

use App\Enums\MonitoringServiceStatus;

/**
 * Uniform output of one health check (docs/operations/monitoring/prd.md §10.2). `message` must be
 * safe to display; `metadata` is sanitized before persistence.
 */
final readonly class HealthCheckResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public MonitoringServiceStatus $status,
        public string $message,
        public ?int $responseTimeMs = null,
        public ?int $httpStatus = null,
        public array $metadata = [],
    ) {}

    /** @param array<string, mixed> $metadata */
    public static function healthy(string $message = 'OK', array $metadata = []): self
    {
        return new self(MonitoringServiceStatus::Healthy, $message, metadata: $metadata);
    }

    public static function notConfigured(string $message): self
    {
        return new self(MonitoringServiceStatus::Unknown, $message, metadata: ['not_configured' => true]);
    }

    public function withTiming(int $responseTimeMs): self
    {
        return new self($this->status, $this->message, $this->responseTimeMs ?? $responseTimeMs, $this->httpStatus, $this->metadata);
    }
}
