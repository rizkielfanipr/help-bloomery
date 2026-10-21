<?php

namespace App\Services\Ordering;

use App\Enums\OrderingSyncStatus;

/**
 * Normalized outcome of one ESB read: rows plus whether the read is complete, failed and worth a
 * retry (timeout, 429, 5xx), or failed for a reason a retry cannot fix (validation, mapping,
 * branch mismatch). An empty successful read is `Completed` with no rows — never a failure.
 */
final readonly class OrderingSourceResult
{
    /** @param list<array<string, mixed>> $rows */
    private function __construct(
        public OrderingSyncStatus $status,
        public array $rows,
        public int $pages,
        public ?string $error,
        public bool $retryable,
    ) {}

    /** @param list<array<string, mixed>> $rows */
    public static function completed(array $rows, int $pages = 1): self
    {
        return new self(OrderingSyncStatus::Completed, $rows, $pages, null, false);
    }

    public static function failed(string $error, bool $retryable): self
    {
        return new self(OrderingSyncStatus::Failed, [], 0, $error, $retryable);
    }
}
