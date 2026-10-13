<?php

namespace App\Services;

use App\Models\Branch;
use InvalidArgumentException;

/**
 * docs/daily-briefing-bundle-approval-prd.md §11/§24/§25: picks the calculator for the active scoring
 * method so the cutover (and its rollback) is a config change, while the compare tooling can still
 * ask for a specific version.
 */
class BriefingScoringMethod
{
    public function __construct(
        private readonly BriefingScoreCalculator $perItem,
        private readonly BriefingBundleScoreCalculator $bundles,
    ) {}

    public function activeVersion(): int
    {
        return (int) config('briefing.scoring_method_version', BriefingScoreResult::METHOD_VERSION);
    }

    public function calculate(Branch $branch, int $year, int $month, ?int $version = null): BriefingScoreResult
    {
        return match ($version ?? $this->activeVersion()) {
            BriefingScoreResult::METHOD_VERSION => $this->perItem->calculate($branch, $year, $month),
            BriefingScoreResult::BUNDLE_METHOD_VERSION => $this->bundles->calculate($branch, $year, $month),
            default => throw new InvalidArgumentException('Unknown Daily Briefing scoring method version.'),
        };
    }
}
