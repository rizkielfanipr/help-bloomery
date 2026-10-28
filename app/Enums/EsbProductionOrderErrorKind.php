<?php

namespace App\Enums;

/**
 * Error classes of a Production Order read (docs/products/kitchen/production-schedule/prd.md §27.3).
 * Only `Retryable` is retried by the queue; every other class needs a fix outside the job.
 */
enum EsbProductionOrderErrorKind: string
{
    case Retryable = 'retryable';
    case Authentication = 'authentication';
    case Validation = 'validation';
    case Configuration = 'configuration';
    case Malformed = 'malformed';

    public function isRetryable(): bool
    {
        return $this === self::Retryable;
    }
}
