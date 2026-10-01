<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Models\Branch;
use App\Models\BranchEsbCode;

/**
 * Result of resolving one Branch's ESB mapping for R&D Internal Memo
 * (docs/rnd-internal-memo-multi-branch-prd.md §7.2, §8). `blockedReason` is the exact, specific,
 * credential-free message the PRD requires ("Mapping ESB belum lengkap", "Static token BLO15
 * belum tersedia", etc.) to show next to a disabled Branch in the picker.
 */
final class MemoBranchMappingResolution
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
