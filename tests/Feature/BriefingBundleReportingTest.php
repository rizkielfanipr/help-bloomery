<?php

use App\Actions\Briefing\RecomputeBriefingScoresAction;
use App\Enums\BriefingBundleStatus;
use App\Filament\Casual\Pages\BriefingHistoryPage;
use App\Filament\Casual\Pages\BriefingScorePage;
use App\Filament\Helpdesk\Pages\BriefingScoreWorkspacePage;
use App\Filament\Helpdesk\Pages\BriefingSettingsPage;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;
use App\Models\BriefingScore;
use App\Models\BriefingSettings;
use App\Models\BriefingTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * docs/daily-briefing-bundle-approval-prd.md Phase 7: every score surface reads the same stored
 * method_version 3 snapshot, Monitoring exports one row per bundle within the same branch scope,
 * Employee App history shows the branch bundle, and settings explain the active method.
 */
function reportingBranch(): Branch
{
    $branch = Branch::factory()->create();
    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id, 'started_at' => '2025-01-01']);

    return $branch;
}

function reportingBundle(Branch $branch, string $date, BriefingBundleStatus $status, string $taskKey = 'report_point'): BriefingRecord
{
    $bundle = BriefingRecord::factory()->forBranch($branch)->onDate($date)->create(['period_start' => $date, 'period_end' => $date, 'status' => $status->value]);
    BriefingRecordRequirement::factory()->for($bundle, 'record')->create(['task_key' => $taskKey, 'label' => 'Poin Laporan']);
    BriefingItem::factory()->for($bundle, 'record')->forTask($taskKey)->create();

    return $bundle;
}

/**
 * @return list<list<mixed>>
 */
function xlsxSheet(string $path, int $sheetIndex): array
{
    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $index => $sheet) {
        if ($index !== $sheetIndex + 1) {
            continue;
        }

        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    $reader->close();

    return $rows;
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00:00'));
    BriefingSettings::instance()->update(['scoring_started_at' => '2020-01-01', 'passing_score' => 80]);
});

describe('score surfaces under method_version 3', function () {
    beforeEach(function () {
        config(['briefing.scoring_method_version' => 3]);
        $this->branch = reportingBranch();
        BriefingTask::create(['key' => 'report_point', 'label' => 'Poin Laporan', 'period' => 'daily', 'submission_type' => 'camera_only', 'is_active' => true, 'include_in_score' => true, 'sort_order' => 1]);

        foreach (range(1, 20) as $day) {
            reportingBundle($this->branch, sprintf('2026-09-%02d', $day), BriefingBundleStatus::Approved);
        }

        app(RecomputeBriefingScoresAction::class)->process(collect([$this->branch]), 2026, 9);
        $this->score = BriefingScore::where('branch_id', $this->branch->id)->sole();
    });

    it('stores one method_version 3 snapshot with approved, expected and missing counts', function () {
        expect($this->score->methodVersion())->toBe(3)
            ->and($this->score->periodBreakdown('daily')['approved'])->toBe(20)
            ->and($this->score->periodBreakdown('daily')['expected'])->toBe(30)
            ->and($this->score->periodBreakdown('daily')['statuses']['missing'])->toBe(10)
            ->and((float) $this->score->score)->toBe(66.67);
    });

    it('shows the same numbers in the workspace detail, the Employee App score page and its PDF', function () {
        Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
        $hrd = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
        $hrd->assignRole('HRD_STAFF');
        actingAs($hrd);

        Livewire::test(BriefingScoreWorkspacePage::class)
            ->set('year', 2026)
            ->set('month', 9)
            ->call('viewDetail', $this->branch->id)
            ->assertSee('20/30')
            ->assertSee('Not Submitted')
            ->assertSeeHtml('aria-label="Rincian harian"')
            ->assertSeeHtml('title="01 Sep 2026 · ');

        $staff = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);
        $staff->assignRole('STORE_STAFF');
        actingAs($staff);

        Livewire::test(BriefingScorePage::class)->assertSee('20/30 disetujui')->assertSee('10 tidak dikirim');

        $pdfHtml = view('exports.casual-briefing-score-pdf', ['score' => $this->score, 'periodLabel' => 'September 2026'])->render();

        expect($pdfHtml)->toContain('20/30 disetujui')->toContain('66.67');
    });

    it('exports the same score in the admin Excel export', function () {
        $hrd = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
        $hrd->assignRole('HRD_STAFF');
        actingAs($hrd);

        $response = get(route('helpdesk.exports.briefing-scores', ['branch_id' => $this->branch->id, 'year' => 2026, 'month' => 9, 'format' => 'excel']))->assertSuccessful();

        $cells = collect(xlsxSheet($response->getFile()->getPathname(), 0))->flatten()->map(fn ($cell): string => (string) $cell);

        expect($cells->contains(fn (string $cell): bool => str_contains($cell, '66.67')))->toBeTrue();
    });
});

describe('Employee App history', function () {
    it('shows the branch bundle to every participant and keeps own legacy rows, but nothing from other branches', function () {
        $branch = reportingBranch();
        $starter = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
        $starter->assignRole('STORE_STAFF');
        $teammate = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
        $teammate->assignRole('STORE_STAFF');

        $bundle = BriefingRecord::factory()->submittedBy($starter)->onDate('2026-10-13')->create(['period_start' => '2026-10-13', 'status' => BriefingBundleStatus::SupervisorReview->value]);
        $ownLegacy = BriefingRecord::factory()->submittedBy($teammate)->onDate('2026-09-01')->create();
        $starterLegacy = BriefingRecord::factory()->submittedBy($starter)->onDate('2026-09-02')->create();
        $otherBranch = reportingBundle(reportingBranch(), '2026-10-13', BriefingBundleStatus::Approved);

        actingAs($teammate);
        $ids = Livewire::test(BriefingHistoryPage::class)->instance()->records()->getCollection()->modelKeys();

        expect($ids)->toContain($bundle->id, $ownLegacy->id)
            ->not->toContain($starterLegacy->id, $otherBranch->id)
            ->and(Livewire::test(BriefingHistoryPage::class)->instance()->summary($bundle)['label'])->toBe('Pending Review');
    });

    it('is closed to users without Employee App briefing access', function () {
        actingAs(User::factory()->create(['is_active' => true]));

        expect(BriefingHistoryPage::canAccess())->toBeFalse();
    });
});

it('explains the active scoring method on the settings page without letting it be changed there', function (int $version, string $label) {
    config(['briefing.scoring_method_version' => $version]);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $hrd = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $hrd->assignRole('HRD_STAFF');
    actingAs($hrd);

    Livewire::test(BriefingSettingsPage::class)
        ->assertSee("Metode aktif: v{$version}")
        ->assertSee($label)
        ->assertSee('ditentukan oleh konfigurasi deployment', false);
})->with([
    'per-item default' => [2, 'Poin disetujui per kemunculan'],
    'bundle cutover' => [3, 'Bundle disetujui'],
]);
