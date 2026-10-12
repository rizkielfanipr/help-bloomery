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
    ) {}

    public static function notApplicable(int $branchId, int $year, int $month, string $periodStart, string $periodEnd, CarbonInterface $computedAt): self
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
            'method_version' => self::METHOD_VERSION,
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
