<?php

namespace App\Actions\StoreSalesOrder;

use App\Models\Branch;
use App\Models\BranchEsbCode;

/**
 * Result of resolving one Branch's ESB mapping for Store Sales Order
 * (docs/store-sales-order-prd.md §8). `blockedReason` is the specific, credential-free message
 * shown next to a disabled Branch in the picker (§8.6).
 */
final class SalesOrderBranchMappingResolution
{
    private function __construct(
        public readonly Branch $branch,
        public readonly ?BranchEsbCode $mapping,
        public readonly ?string $blockedReason,
    ) {}

    public static function resolved(Branch $branch, BranchEsbCode $mapping): self
    {
        return new self($branch, $mapping, null);
    }

    public static function blocked(Branch $branch, string $reason): self
    {
        return new self($branch, null, $reason);
    }

    public function isResolved(): bool
    {
        return $this->mapping !== null;
    }
}
