<?php

use App\Enums\SalesReportStatus;
use App\Filament\Helpdesk\Pages\SalesReportScoresPage;
use App\Models\Branch;
use App\Models\SalesReport;
use App\Models\User;
use App\Services\SalesReportScoreCalculator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SalesReportAssessmentPermissionSeeder;
use Database\Seeders\SalesReportAssessmentStartDateSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 17)->startOfDay());
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SalesReportAssessmentPermissionSeeder::class);
    $this->branch = Branch::factory()->create(['sales_assessment_started_at' => '2026-09-01']);
    $this->user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $this->user->givePermissionTo(['access backoffice', 'view sales report scores', 'edit sales report assessment settings']);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
});

it('scores each required date once and excludes today', function () {
    foreach ([SalesReportStatus::PendingFinance, SalesReportStatus::Completed, SalesReportStatus::Rejected, SalesReportStatus::PendingSupervisor, SalesReportStatus::Draft] as $i => $status) {
        SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-09-'.str_pad($i + 1, 2, '0', STR_PAD_LEFT), 'status' => $status]);
    }
    SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-09-17', 'status' => SalesReportStatus::Completed]);
    $result = app(SalesReportScoreCalculator::class)->calculate(Branch::whereKey($this->branch->id)->get(), '2026-09')[$this->branch->id];
    expect($result)->toMatchArray(['required' => 16, 'passed' => 2, 'rejected' => 1, 'pending' => 2, 'missing' => 11, 'score' => 12.5]);
    expect($result['days'][16])->toMatchArray(['required' => false, 'score' => null, 'reason' => 'Berjalan']);
    expect($result['days'])->toHaveCount(30);
});

it('respects assessment starts exceptions and unconfigured branches', function () {
    $this->branch->update(['sales_assessment_started_at' => '2026-09-10', 'sales_assessment_excluded_dates' => [['date' => '2026-09-12', 'reason' => 'Tutup renovasi']]]);
    $other = Branch::factory()->create();
    $result = app(SalesReportScoreCalculator::class)->calculate(Branch::whereIn('id', [$this->branch->id, $other->id])->get(), '2026-09');
    expect($result[$this->branch->id]['required'])->toBe(6)->and($result[$this->branch->id]['days'][11]['reason'])->toBe('Tutup renovasi');
    expect($result[$other->id]['score'])->toBeNull()->and($result[$other->id]['required'])->toBe(0);
});

it('counts leap months and attributes late approval to report month', function () {
    $this->branch->update(['sales_assessment_started_at' => '2024-02-01']);
    $report = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2024-02-29', 'status' => SalesReportStatus::PendingSupervisor]);
    $calculate = fn () => app(SalesReportScoreCalculator::class)->calculate(Branch::whereKey($this->branch->id)->get(), '2024-02')[$this->branch->id];
    expect($calculate()['required'])->toBe(29)->and($calculate()['passed'])->toBe(0);
    $report->update(['status' => SalesReportStatus::PendingFinance, 'supervisor_reviewed_at' => now()]);
    expect($calculate()['passed'])->toBe(1)->and($calculate()['score'])->toBe(3.45);
    $report->update(['status' => SalesReportStatus::Completed]);
    expect($calculate()['passed'])->toBe(1);
    $report->update(['status' => SalesReportStatus::Rejected]);
    expect($calculate()['passed'])->toBe(0);
});

it('shows finance page and restricts branch settings and details', function () {
    $other = Branch::factory()->create(['name' => 'Forbidden Branch']);
    $this->actingAs($this->user)->get(SalesReportScoresPage::getUrl())->assertSuccessful()->assertSee('Reporting Compliance')->assertDontSee('Forbidden Branch');
    Livewire::test(SalesReportScoresPage::class)->call('showDetail', $this->branch->id)->assertSee('Rincian Harian');
    expect(fn () => Livewire::test(SalesReportScoresPage::class)->call('openSettings', $other->id))->toThrow(ModelNotFoundException::class);
    expect(fn () => Livewire::test(SalesReportScoresPage::class)->call('showDetail', $other->id))->toThrow(ModelNotFoundException::class);
    Livewire::test(SalesReportScoresPage::class)->set('branchFilter', (string) $other->id)->assertDontSee('Forbidden Branch');
});

it('validates and persists settings without changing reports', function () {
    $this->actingAs($this->user);
    Livewire::test(SalesReportScoresPage::class)->call('openSettings', $this->branch->id)
        ->set('assessmentStart', '2026-08-01')->set('excludedDates', [['date' => '2026-09-01', 'reason' => '']])
        ->call('saveSettings')->assertHasErrors('excludedDates.0.reason')
        ->set('excludedDates.0.reason', 'Tutup operasional')->call('saveSettings')->assertHasNoErrors()->assertSet('settingsBranchId', null);
    expect($this->branch->fresh()->sales_assessment_started_at->toDateString())->toBe('2026-08-01');
    expect(SalesReport::count())->toBe(0);
    Livewire::test(SalesReportScoresPage::class)->call('openSettings', $this->branch->id)
        ->set('excludedDates', [['date' => '2026-09-01', 'reason' => 'A'], ['date' => '2026-09-01', 'reason' => 'B']])
        ->call('saveSettings')->assertHasErrors('excludedDates.0.date');
});

it('requires dedicated view and edit permissions', function () {
    $this->actingAs($this->user);
    $this->user->revokePermissionTo('edit sales report assessment settings');
    Livewire::test(SalesReportScoresPage::class)->assertDontSee('Pengaturan</button>', false)->call('openSettings', $this->branch->id)->assertForbidden();
    Livewire::test(SalesReportScoresPage::class)->call('saveSettings')->assertForbidden();
    $this->user->revokePermissionTo('view sales report scores');
    $this->get(SalesReportScoresPage::getUrl())->assertForbidden();
});

it('shows partial shifts as pending with zero score', function () {
    $this->branch->update(['sales_shift_count' => 2]);
    $report = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-09-01', 'status' => SalesReportStatus::Draft]);
    $report->shiftSubmissions()->create(['shift_number' => 1, 'submitted_by' => $this->user->id, 'submitted_at' => now()]);
    $result = app(SalesReportScoreCalculator::class)->calculate(Branch::whereKey($this->branch->id)->get(), '2026-09')[$this->branch->id];
    expect($result['days'][0])->toMatchArray(['score' => 0, 'submitted_shifts' => 1, 'required_shifts' => 2]);
    expect($result['pending'])->toBe(1)->and($result['passed'])->toBe(0);
});

it('has no score for future months and includes all elapsed calendar dates', function () {
    $calculate = fn (string $month) => app(SalesReportScoreCalculator::class)->calculate(Branch::whereKey($this->branch->id)->get(), $month)[$this->branch->id];
    expect($calculate('2026-10')['required'])->toBe(0)->and($calculate('2026-10')['score'])->toBeNull();
    $this->branch->update(['sales_assessment_started_at' => '2026-08-01']);
    expect($calculate('2026-08')['required'])->toBe(31)->and($calculate('2026-08')['missing'])->toBe(31);
});

it('allows explicitly authorized users to view all branches', function () {
    $other = Branch::factory()->create(['name' => 'Additional Branch']);
    $this->user->update(['access_all_branches' => true]);
    $this->actingAs($this->user);
    Livewire::test(SalesReportScoresPage::class)->assertSee('Additional Branch')->call('openSettings', $other->id)->assertSet('settingsBranchId', $other->id);
});

it('standardizes all existing branch start dates without changing reports or exceptions', function () {
    $exceptions = [['date' => '2026-09-03', 'reason' => 'Tutup renovasi']];
    $this->branch->update(['sales_assessment_started_at' => '2026-08-01', 'sales_assessment_excluded_dates' => $exceptions]);
    $inactiveBranch = Branch::factory()->create(['is_active' => false]);
    $report = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-09-01', 'status' => SalesReportStatus::Completed]);
    $before = $report->fresh()->getRawOriginal();
    $this->seed(SalesReportAssessmentStartDateSeeder::class);
    $this->seed(SalesReportAssessmentStartDateSeeder::class);
    expect($this->branch->fresh()->sales_assessment_started_at->toDateString())->toBe('2026-09-01');
    expect($inactiveBranch->fresh()->sales_assessment_started_at->toDateString())->toBe('2026-09-01');
    expect($this->branch->fresh()->sales_assessment_excluded_dates)->toBe($exceptions);
    expect($report->fresh()->getRawOriginal())->toBe($before);
});

it('opens daily details and settings as mutually exclusive dismissible modals', function () {
    $this->actingAs($this->user);
    Livewire::test(SalesReportScoresPage::class)
        ->assertDontSee('data-testid="daily-score-modal"', false)
        ->assertDontSee('data-testid="score-settings-modal"', false)
        ->call('showDetail', $this->branch->id)->assertSee('data-testid="daily-score-modal"', false)
        ->call('closeDetail')->assertSet('detailBranchId', null)->assertDontSee('Rincian Harian')
        ->call('openSettings', $this->branch->id)->assertSee('data-testid="score-settings-modal"', false)
        ->call('showDetail', $this->branch->id)->assertSet('settingsBranchId', null)
        ->call('openSettings', $this->branch->id)->assertSet('detailBranchId', null)
        ->call('closeSettings')->assertDontSee('data-testid="score-settings-modal"', false)
        ->call('showDetail', $this->branch->id)->set('month', '2026-08')->assertSet('detailBranchId', null)
        ->call('showDetail', $this->branch->id)->set('branchFilter', (string) $this->branch->id)->assertSet('detailBranchId', null);
});
