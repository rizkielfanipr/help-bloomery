<?php

use App\Enums\SalesReportStatus;
use App\Filament\Helpdesk\Pages\SalesReportSettingsPage;
use App\Models\Branch;
use App\Models\SalesReport;
use App\Models\SalesReportSettings;
use App\Models\User;
use App\Services\SalesReportScoreCalculator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SalesReportSettingsPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 17)->setTime(14, 0));
    $this->branch = Branch::factory()->create(['sales_assessment_started_at' => '2026-09-01']);
    $this->settings = SalesReportSettings::instance();
});

it('is disabled by default and changes no reports', function () {
    $report = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-09-01', 'submitted_at' => now()->subDays(10)]);
    $this->artisan('sales-reports:auto-reject')->assertSuccessful();
    expect($report->fresh()->status)->toBe(SalesReportStatus::PendingSupervisor)->and($report->approvals()->count())->toBe(0);
});

it('rejects exactly at the configured boundary and records a system audit once', function () {
    $this->settings->update(['auto_reject_enabled' => true, 'auto_reject_after_days' => 4, 'auto_reject_reason' => 'Belum approved setelah :days hari.']);
    $report = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-09-13', 'submitted_at' => now()->subDays(4), 'revision_number' => 2]);
    $within = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-09-14', 'submitted_at' => now()->subDays(4)->addSecond()]);
    $this->artisan('sales-reports:auto-reject')->assertSuccessful();
    $this->artisan('sales-reports:auto-reject')->assertSuccessful();
    expect($report->fresh()->status)->toBe(SalesReportStatus::RejectedBySystem)->and($within->fresh()->status)->toBe(SalesReportStatus::PendingSupervisor);
    expect($report->fresh()->supervisor_note)->toBe('Belum approved setelah 4 hari.')->and($report->fresh()->supervisor_reviewed_by)->toBeNull();
    expect($report->approvals()->count())->toBe(1);
    $approval = $report->approvals()->first();
    expect($approval->actor_id)->toBeNull()->and($approval->stage)->toBe('supervisor')->and($approval->revision_number)->toBe(2)
        ->and($approval->metadata['source'])->toBe('system')->and($approval->metadata['auto_reject_after_days'])->toBe(4);
    $score = app(SalesReportScoreCalculator::class)->calculate(Branch::whereKey($this->branch->id)->get(), '2026-09')[$this->branch->id];
    expect($score['rejected'])->toBe(1)->and($score['days'][12]['score'])->toBe(0);
    expect($report->fresh()->status->getLabel())->toBe('Rejected by System')->and($report->fresh()->status->getColor())->toBe('danger');
});

it('protects draft finance completed rejected and reports before the effective date', function () {
    $this->settings->update(['auto_reject_enabled' => true]);
    foreach ([SalesReportStatus::Draft, SalesReportStatus::PendingFinance, SalesReportStatus::Completed, SalesReportStatus::Rejected, SalesReportStatus::RejectedBySystem] as $i => $status) {
        $report = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-09-0'.($i + 1), 'status' => $status, 'submitted_at' => now()->subDays(10)]);
        $this->artisan('sales-reports:auto-reject')->assertSuccessful();
        expect($report->fresh()->status)->toBe($status);
    }
    $older = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-08-31', 'submitted_at' => now()->subDays(10)]);
    $noTimestamp = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-09-06', 'submitted_at' => null]);
    $this->artisan('sales-reports:auto-reject')->assertSuccessful();
    expect($older->fresh()->status)->toBe(SalesReportStatus::PendingSupervisor)->and($noTimestamp->fresh()->status)->toBe(SalesReportStatus::PendingSupervisor);
    expect(SalesReport::count())->toBe(7);
});

it('rechecks eligibility when a candidate was approved during processing', function () {
    $this->settings->update(['auto_reject_enabled' => true]);
    $report = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-09-01', 'submitted_at' => now()->subDays(10)]);
    SalesReport::retrieved(function (SalesReport $candidate) use ($report): void {
        if ($candidate->id === $report->id && ! array_key_exists('status', $candidate->getAttributes())) {
            SalesReport::whereKey($candidate->id)->update(['status' => SalesReportStatus::PendingFinance]);
        }
    });
    try {
        $this->artisan('sales-reports:auto-reject')->assertSuccessful();
        expect($report->fresh()->status)->toBe(SalesReportStatus::PendingFinance)->and($report->approvals()->count())->toBe(0);
    } finally {
        SalesReport::flushEventListeners();
        SalesReport::clearBootedModels();
    }
});

it('saves arbitrary integer days without immediately rejecting and shows overdue counts', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SalesReportSettingsPermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['access backoffice', 'edit sales report settings']);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $report = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-09-01', 'submitted_at' => now()->subDays(10)]);
    $this->actingAs($user)->get(SalesReportSettingsPage::getUrl())->assertSuccessful()->assertDontSee('Finance · Configuration')->assertDontSee('laporan melewati batas berdasarkan pengaturan tersimpan')->assertSee('Waktu &amp; Periode', false)->assertSee('Informasi Penolakan')->assertSee('Informasi Proses Otomatis')->assertSee('id="reject-days"', false)->assertSee('wire:model="data.auto_reject_after_days"', false);
    Livewire::test(SalesReportSettingsPage::class)->fillForm(['auto_reject_enabled' => true, 'auto_reject_after_days' => 4,
        'effective_from' => '2026-09-01', 'auto_reject_reason' => 'Tidak approved :days hari.'])->call('save')->assertHasNoFormErrors();
    expect($this->settings->fresh()->auto_reject_after_days)->toBe(4)->and($this->settings->fresh()->auto_reject_enabled)->toBeTrue();
    expect($report->fresh()->status)->toBe(SalesReportStatus::PendingSupervisor);
    foreach ([0, -1, 1.5, 'abc'] as $invalid) {
        Livewire::test(SalesReportSettingsPage::class)->fillForm(['auto_reject_after_days' => $invalid])->call('save')->assertHasFormErrors(['auto_reject_after_days']);
    }
    $component = Livewire::test(SalesReportSettingsPage::class);
    $user->revokePermissionTo('edit sales report settings');
    $component->call('save')->assertForbidden();
    $this->get(SalesReportSettingsPage::getUrl())->assertForbidden();
});
