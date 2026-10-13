<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * docs/daily-briefing-scoring-ui-prd.md §10/§11. Immutable output of BriefingScoreCalculator.
 * `toArray()` matches the PRD's result contract exactly; `toBreakdownArray()` is what gets
 * persisted into `briefing_scores.breakdown` so a historical snapshot is fully self-describing
 * (method version, passing score, weights, approved/expected, computed timestamp) without
 * depending on current settings to be re-interpreted later.
 */
final readonly class BriefingScoreResult
{
    public const METHOD_VERSION = 2;

    /** docs/daily-briefing-bundle-approval-prd.md §11: approved bundles over due obligations. */
    public const BUNDLE_METHOD_VERSION = 3;

    public const STATUS_ACHIEVED = 'achieved';

    public const STATUS_NOT_ACHIEVED = 'not_achieved';

    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    /**
     * @param  array<string, array{approved: int, expected: int, rate: float, applicable: bool, configured_weight: float, effective_weight: float, contribution: float, tasks: array<int, array{key: string, label: string, approved: int, expected: int, rate: float}>}>  $periods
     */
    public function __construct(
        public int $branchId,
        public int $year,
        public int $month,
        public float $score,
        public float $passingScore,
        public string $status,
        public bool $isProvisional,
        public string $periodStart,
        public string $periodEnd,
        public array $periods,
        public CarbonInterface $computedAt,
        public int $methodVersion = self::METHOD_VERSION,
    ) {}

    public static function notApplicable(int $branchId, int $year, int $month, string $periodStart, string $periodEnd, CarbonInterface $computedAt, int $methodVersion = self::METHOD_VERSION): self
    {
        return new self(
            branchId: $branchId,
            year: $year,
            month: $month,
            score: 0.0,
            passingScore: 0.0,
            status: self::STATUS_NOT_APPLICABLE,
            isProvisional: false,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            periods: [],
            computedAt: $computedAt,
            methodVersion: $methodVersion,
        );
    }

    /**
     * docs/daily-briefing-scoring-ui-prd.md §10: shared by every method version. A period with no
     * expected occurrence is not applicable and its weight is redistributed proportionally across
     * the applicable ones (equally if none of them carries a configured weight). Rates and
     * contributions round to 2 decimals; the final score is the rounded sum of contributions.
     *
     * @param  array<string, array<string, mixed>>  $periods  each with approved, expected, configured_weight
     */
    public static function fromPeriods(
        int $branchId,
        int $year,
        int $month,
        array $periods,
        float $passingScore,
        bool $isProvisional,
        string $periodStart,
        string $periodEnd,
        CarbonInterface $computedAt,
        int $methodVersion,
    ): self {
        foreach ($periods as &$data) {
            $data['applicable'] = $data['expected'] > 0;
            $data['rate'] = $data['applicable'] ? round($data['approved'] / $data['expected'] * 100, 2) : 0.0;
        }
        unset($data);

        $applicable = array_filter($periods, fn (array $period): bool => $period['applicable']);

        if ($applicable === []) {
            return self::notApplicable($branchId, $year, $month, $periodStart, $periodEnd, $computedAt, $methodVersion);
        }

        $activeWeightSum = array_sum(array_column($applicable, 'configured_weight'));
        $finalScore = 0.0;

        foreach ($periods as &$data) {
            if (! $data['applicable']) {
                $data['effective_weight'] = 0.0;
                $data['contribution'] = 0.0;

                continue;
            }

            $effectiveWeight = $activeWeightSum > 0
                ? ($data['configured_weight'] / $activeWeightSum) * 100
                : 100 / count($applicable);

            $data['effective_weight'] = round($effectiveWeight, 2);
            $data['contribution'] = round($data['rate'] * $effectiveWeight / 100, 2);
            $finalScore += $data['contribution'];
        }
        unset($data);

        $finalScore = round($finalScore, 2);

        return new self(
            branchId: $branchId,
            year: $year,
            month: $month,
            score: $finalScore,
            passingScore: $passingScore,
            status: $finalScore >= $passingScore ? self::STATUS_ACHIEVED : self::STATUS_NOT_ACHIEVED,
            isProvisional: $isProvisional,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            periods: $periods,
            computedAt: $computedAt,
            methodVersion: $methodVersion,
        );
    }

    public function isNotApplicable(): bool
    {
        return $this->status === self::STATUS_NOT_APPLICABLE;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'method_version' => $this->methodVersion,
            'branch_id' => $this->branchId,
            'year' => $this->year,
            'month' => $this->month,
            'score' => $this->score,
            'passing_score' => $this->passingScore,
            'status' => $this->status,
            'is_provisional' => $this->isProvisional,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'periods' => $this->periods,
        ];
    }

    /** @return array<string, mixed> */
    public function toBreakdownArray(): array
    {
        return [
            ...$this->toArray(),
            'computed_at' => $this->computedAt->toIso8601String(),
        ];
    }
}
