<?php

use App\Actions\Briefing\ReopenBriefingBundleAction;
use App\Actions\Briefing\ReviewBriefingBundleAction;
use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingPeriod;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingItem;
use App\Models\BriefingPeriodWeight;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;
use App\Models\BriefingScore;
use App\Models\BriefingSettings;
use App\Models\BriefingTask;
use App\Models\User;
use App\Services\BriefingBundleScoreCalculator;
use App\Services\BriefingScoreResult;
use App\Services\BriefingScoringMethod;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * docs/daily-briefing-bundle-approval-prd.md Phase 6: method_version 3 scores approved bundles over
 * due obligations; method_version 2 stays the active default until the cutover is approved.
 * "Now" is Wednesday 2026-10-14 10:00.
 */
function scoringBranch(array $participation = []): Branch
{
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(array_merge(['branch_id' => $branch->id, 'started_at' => '2025-01-01'], $participation));

    return $branch;
}

function scoringTask(BriefingPeriod $period, array $overrides = []): BriefingTask
{
    static $n = 0;
    $n++;

    return BriefingTask::create(array_merge([
        'key' => 'v3_task_'.$n,
        'label' => 'V3 Task '.$n,
        'period' => $period->value,
        'submission_type' => 'camera_only',
        'is_active' => true,
        'include_in_score' => true,
        'sort_order' => $n,
    ], $overrides));
}

function scoringBundle(Branch $branch, BriefingPeriod $period, string $periodStart, BriefingBundleStatus $status, bool $scored = true): BriefingRecord
{
    $bundle = BriefingRecord::factory()->forBranch($branch)->forPeriod($period, $periodStart)->create([
        'period_start' => $periodStart,
        'status' => $status->value,
    ]);
    BriefingRecordRequirement::factory()->for($bundle, 'record')->create(['include_in_score' => $scored]);

    return $bundle;
}

function v3(Branch $branch, int $year, int $month): BriefingScoreResult
{
    return app(BriefingBundleScoreCalculator::class)->calculate($branch, $year, $month);
}

beforeEach(function () {
    Cache::flush();
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00:00'));
    BriefingSettings::instance()->update(['scoring_started_at' => '2020-01-01', 'passing_score' => 80]);
});

describe('denominator', function () {
    it('scores a finished month as approved bundles over every expected day', function () {
        $branch = scoringBranch();
        scoringTask(BriefingPeriod::Daily);

        foreach (range(1, 27) as $day) {
            scoringBundle($branch, BriefingPeriod::Daily, sprintf('2026-09-%02d', $day), BriefingBundleStatus::Approved);
        }
        scoringBundle($branch, BriefingPeriod::Daily, '2026-09-28', BriefingBundleStatus::Rejected);
        scoringBundle($branch, BriefingPeriod::Daily, '2026-09-29', BriefingBundleStatus::SupervisorReview);

        $result = v3($branch, 2026, 9);

        expect($result->methodVersion)->toBe(3)
            ->and($result->toArray()['method_version'])->toBe(3)
            ->and($result->periods['daily']['approved'])->toBe(27)
            ->and($result->periods['daily']['expected'])->toBe(30)
            ->and($result->periods['daily']['rate'])->toBe(90.0)
            ->and($result->periods['daily']['statuses'])->toMatchArray(['approved' => 27, 'rejected' => 1, 'supervisor_review' => 1, 'missing' => 1, 'draft' => 0])
            ->and($result->periods['daily']['tasks'])->toBe([])
            ->and($result->score)->toBe(90.0)
            ->and($result->isProvisional)->toBeFalse();
    });

    it('uses the whole running month as the denominator and marks periods not yet ended as upcoming', function () {
        $branch = scoringBranch();
        scoringTask(BriefingPeriod::Daily);
        scoringTask(BriefingPeriod::Weekly);
        scoringTask(BriefingPeriod::Monthly);
        scoringBundle($branch, BriefingPeriod::Daily, '2026-10-14', BriefingBundleStatus::Approved);
        scoringBundle($branch, BriefingPeriod::Weekly, '2026-10-12', BriefingBundleStatus::Approved);

        $result = v3($branch, 2026, 10);

        expect($result->isProvisional)->toBeTrue()
            ->and($result->periodEnd)->toBe('2026-10-31')
            ->and($result->periods['daily']['expected'])->toBe(31)
            ->and($result->periods['daily']['approved'])->toBe(1)
            ->and($result->periods['daily']['statuses'])->toMatchArray(['approved' => 1, 'missing' => 13, 'upcoming' => 17])
            ->and($result->periods['weekly']['expected'])->toBe(4)
            ->and($result->periods['weekly']['approved'])->toBe(1)
            ->and($result->periods['weekly']['statuses'])->toMatchArray(['approved' => 1, 'missing' => 1, 'upcoming' => 2])
            ->and($result->periods['monthly']['expected'])->toBe(1)
            ->and($result->periods['monthly']['applicable'])->toBeTrue()
            ->and($result->periods['monthly']['statuses'])->toMatchArray(['upcoming' => 1]);
    });

    it('lists every occurrence with its period, status, and bundle for the score detail', function () {
        $branch = scoringBranch();
        scoringTask(BriefingPeriod::Daily);
        scoringTask(BriefingPeriod::Weekly);
        $approved = scoringBundle($branch, BriefingPeriod::Daily, '2026-10-01', BriefingBundleStatus::Approved);
        scoringBundle($branch, BriefingPeriod::Daily, '2026-10-02', BriefingBundleStatus::Rejected);

        $result = v3($branch, 2026, 10);
        $daily = $result->periods['daily']['occurrences'];

        expect($daily)->toHaveCount(31)
            ->and($daily[0])->toBe(['start' => '2026-10-01', 'end' => '2026-10-01', 'status' => 'approved', 'bundle_id' => $approved->id])
            ->and($daily[1]['status'])->toBe('rejected')
            ->and($daily[2])->toBe(['start' => '2026-10-03', 'end' => '2026-10-03', 'status' => 'missing', 'bundle_id' => null])
            ->and($daily[30]['status'])->toBe('upcoming')
            ->and(collect($result->periods['weekly']['occurrences'])->pluck('end')->all())->toBe(['2026-10-11', '2026-10-18', '2026-10-25', '2026-11-01']);
    });

    it('respects participation that starts or ends inside the month', function (array $participation, int $expectedDays) {
        $branch = scoringBranch($participation);
        scoringTask(BriefingPeriod::Daily);

        expect(v3($branch, 2026, 9)->periods['daily']['expected'])->toBe($expectedDays);
    })->with([
        'activated on the 16th' => [['started_at' => '2026-09-16'], 15],
        'ended on the 10th' => [['ended_at' => '2026-09-10'], 10],
    ]);

    it('gives weeks to the month of their Monday, including one that ends in the next month', function () {
        $branch = scoringBranch();
        scoringTask(BriefingPeriod::Weekly);
        scoringBundle($branch, BriefingPeriod::Weekly, '2026-09-28', BriefingBundleStatus::Approved);

        $september = v3($branch, 2026, 9)->periods['weekly'];

        expect($september['expected'])->toBe(4)
            ->and($september['approved'])->toBe(1)
            ->and(v3($branch, 2026, 10)->periods['weekly']['approved'])->toBe(0);
    });

    it('treats legacy ungrouped rows as missing and ignores other branches\' bundles', function () {
        $branch = scoringBranch();
        scoringTask(BriefingPeriod::Daily);
        BriefingRecord::factory()->forBranch($branch)->onDate('2026-09-01')->create();
        scoringBundle(scoringBranch(), BriefingPeriod::Daily, '2026-09-02', BriefingBundleStatus::Approved);

        $daily = v3($branch, 2026, 9)->periods['daily'];

        expect($daily['approved'])->toBe(0)
            ->and($daily['statuses']['missing'])->toBe(30);
    });

    it('drops an occurrence whose bundle snapshot holds no scored point', function () {
        $branch = scoringBranch();
        scoringTask(BriefingPeriod::Daily);
        scoringBundle($branch, BriefingPeriod::Daily, '2026-09-01', BriefingBundleStatus::Approved, scored: false);

        expect(v3($branch, 2026, 9)->periods['daily']['expected'])->toBe(29);
    });

    it('is not applicable, never zero, when nothing is expected', function (string $case) {
        $branch = match ($case) {
            'participant without scored points' => scoringBranch(),
            'non-participant branch' => tap(Branch::factory()->create(), fn () => scoringTask(BriefingPeriod::Daily)),
            'month entirely in the future' => tap(scoringBranch(), fn () => scoringTask(BriefingPeriod::Daily)),
        };

        $result = $case === 'month entirely in the future' ? v3($branch, 2026, 12) : v3($branch, 2026, 9);

        expect($result->isNotApplicable())->toBeTrue()
            ->and($result->methodVersion)->toBe(3);
    })->with(['participant without scored points', 'non-participant branch', 'month entirely in the future']);
});

describe('weighting and rounding', function () {
    it('weights the period rates with the default or branch override weights', function (?array $override, float $expectedScore) {
        $branch = scoringBranch();
        scoringTask(BriefingPeriod::Daily);
        scoringTask(BriefingPeriod::Weekly);
        scoringTask(BriefingPeriod::Monthly);

        if ($override !== null) {
            BriefingPeriodWeight::create(['branch_id' => $branch->id, ...$override]);
        }

        foreach (range(1, 30) as $day) {
            scoringBundle($branch, BriefingPeriod::Daily, sprintf('2026-09-%02d', $day), BriefingBundleStatus::Approved);
        }
        scoringBundle($branch, BriefingPeriod::Weekly, '2026-09-07', BriefingBundleStatus::Approved);
        scoringBundle($branch, BriefingPeriod::Weekly, '2026-09-14', BriefingBundleStatus::Approved);
        scoringBundle($branch, BriefingPeriod::Monthly, '2026-09-01', BriefingBundleStatus::Approved);

        expect(v3($branch, 2026, 9)->score)->toBe($expectedScore);
    })->with([
        'default 40/30/30' => [null, 85.0],
        'override 50/25/25' => [['daily_weight' => 50, 'weekly_weight' => 25, 'monthly_weight' => 25], 87.5],
    ]);

    it('rounds rates and the final score to two decimals', function () {
        $branch = scoringBranch(['started_at' => '2026-09-28']);
        scoringTask(BriefingPeriod::Daily);
        scoringBundle($branch, BriefingPeriod::Daily, '2026-09-28', BriefingBundleStatus::Approved);
        scoringBundle($branch, BriefingPeriod::Daily, '2026-09-29', BriefingBundleStatus::Approved);

        $result = v3($branch, 2026, 9);

        expect($result->periods['daily']['rate'])->toBe(66.67)
            ->and($result->score)->toBe(66.67);
    });
});

describe('active method and recompute', function () {
    it('keeps method_version 2 active by default and switches only through config', function () {
        $branch = scoringBranch();
        scoringTask(BriefingPeriod::Daily);
        $scoring = app(BriefingScoringMethod::class);

        expect($scoring->activeVersion())->toBe(2)
            ->and($scoring->calculate($branch, 2026, 9)->methodVersion)->toBe(2);

        config(['briefing.scoring_method_version' => 3]);

        expect($scoring->calculate($branch, 2026, 9)->methodVersion)->toBe(3)
            ->and(fn () => $scoring->calculate($branch, 2026, 9, 99))->toThrow(InvalidArgumentException::class);
    });

    it('updates the stored score after an approval and withdraws it again on reopen', function () {
        config(['briefing.scoring_method_version' => 3]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $branch = scoringBranch();
        scoringTask(BriefingPeriod::Daily);
        $bundle = scoringBundle($branch, BriefingPeriod::Daily, '2026-09-15', BriefingBundleStatus::SupervisorReview);
        BriefingItem::factory()->for($bundle, 'record')->forTask($bundle->requirements->sole()->task_key)->create();

        $supervisor = User::factory()->create(['is_active' => true]);
        $supervisor->assignRole('SUPERVISOR_STORE');
        $supervisor->syncBranchAccess([$branch->id], $branch->id);

        app(ReviewBriefingBundleAction::class)->approve($supervisor, $bundle);

        $stored = BriefingScore::where('branch_id', $branch->id)->where('year', 2026)->where('month', 9)->sole();
        expect($stored->methodVersion())->toBe(3)
            ->and($stored->periodBreakdown('daily')['approved'])->toBe(1);

        app(ReopenBriefingBundleAction::class)->execute($supervisor, $bundle, 'Salah foto.');

        expect($stored->refresh()->periodBreakdown('daily')['approved'])->toBe(0)
            ->and(BriefingScore::where('branch_id', '!=', $branch->id)->count())->toBe(0);
    });
});
