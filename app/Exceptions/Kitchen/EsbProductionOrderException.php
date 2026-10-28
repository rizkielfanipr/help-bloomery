<?php

namespace App\Exceptions\Kitchen;

use App\Enums\EsbProductionOrderErrorKind;
use RuntimeException;
use Throwable;

/**
 * A classified Production Order read failure (docs/products/kitchen/production-schedule/prd.md §27.3).
 * The message never contains a token, credential, or response payload.
 */
class EsbProductionOrderException extends RuntimeException
{
    public function __construct(
        public readonly EsbProductionOrderErrorKind $kind,
        string $message,
        public readonly ?int $httpStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function malformed(string $message): self
    {
        return new self(EsbProductionOrderErrorKind::Malformed, $message);
    }

    public static function configuration(string $message): self
    {
        return new self(EsbProductionOrderErrorKind::Configuration, $message);
    }

    public function isRetryable(): bool
    {
        return $this->kind->isRetryable();
    }
}
