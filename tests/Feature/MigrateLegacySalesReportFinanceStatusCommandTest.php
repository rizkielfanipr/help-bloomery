<?php

use App\Actions\SalesReport\RejectCompletedSalesReportAction;
use App\Enums\SalesReportStatus;
use App\Models\Branch;
use App\Models\SalesReport;
use App\Models\SalesReportReconciliation;
use App\Models\User;
use App\Services\SalesReport\SalesReportLegacyFinanceMigrator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    $this->branch = Branch::factory()->create();
    $this->supervisor = User::factory()->create(['is_active' => true]);

    $this->valid = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-09-01', 'status' => SalesReportStatus::PendingFinance,
        'supervisor_reviewed_by' => $this->supervisor->id, 'supervisor_reviewed_at' => now()->subDay(), 'supervisor_note' => 'OK', 'finance_note' => 'Catatan lama']);
    $this->valid->approvals()->create(['stage' => 'supervisor', 'action' => 'approved', 'actor_id' => $this->supervisor->id, 'revision_number' => 0]);
    SalesReportReconciliation::query()->create(['sales_report_id' => $this->valid->id, 'payment_method_name' => 'QRIS', 'reported_store_amount' => 100, 'store_amount' => 100,
        'system_amount' => 100, 'settlement_amount' => 98, 'mdr_amount' => 2]);
    $this->anomaly = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-09-02', 'status' => SalesReportStatus::PendingFinance]);
});

it('previews without changing anything', function () {
    $this->artisan('sales-reports:migrate-legacy-finance-status')
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('Candidate IDs: '.$this->valid->id)
        ->expectsOutputToContain("Exception #{$this->anomaly->id}: No supervisor/approved audit record.")
        ->assertSuccessful();

    expect($this->valid->refresh()->status)->toBe(SalesReportStatus::PendingFinance)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('converts only reports with a valid supervisor approval, keeps finance data, and is idempotent', function () {
    $this->artisan('sales-reports:migrate-legacy-finance-status', ['--execute' => true, '--force' => true])->assertSuccessful();

    $this->valid->refresh();
    $migration = $this->valid->approvals()->where('stage', 'system')->sole();
    expect($this->valid->status)->toBe(SalesReportStatus::Completed)
        ->and($this->valid->supervisor_reviewed_by)->toBe($this->supervisor->id)
        ->and($this->valid->finance_reviewed_by)->toBeNull()
        ->and($this->valid->finance_note)->toBe('Catatan lama')
        ->and((float) $this->valid->reconciliations()->sole()->settlement_amount)->toBe(98.0)
        ->and($migration->metadata)->toMatchArray(['source' => 'workflow_migration', 'previous_status' => 'pending_finance'])
        ->and($this->valid->approvals()->where('stage', 'finance')->exists())->toBeFalse()
        ->and($this->anomaly->refresh()->status)->toBe(SalesReportStatus::PendingFinance)
        ->and(Storage::disk('local')->allFiles(SalesReportLegacyFinanceMigrator::SNAPSHOT_DIRECTORY))->toHaveCount(1);

    $this->artisan('sales-reports:migrate-legacy-finance-status', ['--execute' => true, '--force' => true])->assertSuccessful();
    expect($this->valid->approvals()->where('stage', 'system')->count())->toBe(1);
});

it('restores an unchanged report and refuses one rejected after the migration', function () {
    $other = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => '2026-09-03', 'status' => SalesReportStatus::PendingFinance,
        'supervisor_reviewed_by' => $this->supervisor->id, 'supervisor_reviewed_at' => now()->subDay()]);
    $other->approvals()->create(['stage' => 'supervisor', 'action' => 'approved', 'actor_id' => $this->supervisor->id, 'revision_number' => 0]);

    $snapshot = app(SalesReportLegacyFinanceMigrator::class)->execute()['snapshot'];
    $finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->travel(1)->minutes();
    app(RejectCompletedSalesReportAction::class)->execute($other->refresh(), $finance, 'Ditolak setelah migrasi.');

    $preview = app(SalesReportLegacyFinanceMigrator::class)->restore($snapshot, false);
    expect($preview['restored'])->toBe([$this->valid->id])
        ->and($preview['conflicts'])->toHaveCount(1)
        ->and($this->valid->refresh()->status)->toBe(SalesReportStatus::Completed);

    $this->artisan('sales-reports:migrate-legacy-finance-status', ['--restore' => $snapshot, '--execute' => true, '--force' => true])
        ->expectsOutputToContain("Conflict #{$other->id}")
        ->assertSuccessful();

    expect($this->valid->refresh()->status)->toBe(SalesReportStatus::PendingFinance)
        ->and($other->refresh()->status)->toBe(SalesReportStatus::Rejected);
});
