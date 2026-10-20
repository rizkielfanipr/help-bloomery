<?php

use App\Enums\MonitoringErrorStatus;
use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringCheckResult;
use App\Models\MonitoringDailyAggregate;
use App\Models\MonitoringDeployment;
use App\Models\MonitoringError;
use App\Models\MonitoringIncident;
use App\Models\MonitoringIncidentLink;
use App\Models\MonitoringService;
use App\Models\MonitoringTransaction;
use App\Services\Monitoring\MonitoringAggregator;
use App\Services\Monitoring\MonitoringRetention;

it('applies retention boundaries, keeps incidents and linked records, and is idempotent', function () {
    $service = MonitoringService::factory()->create(['key' => 'database']);
    $oldCheck = MonitoringCheckResult::factory()->create(['monitoring_service_id' => $service->id, 'checked_at' => now()->subDays(31)]);
    $freshCheck = MonitoringCheckResult::factory()->create(['monitoring_service_id' => $service->id, 'checked_at' => now()->subDays(29)]);

    $expiredPayload = MonitoringTransaction::factory()->create(['started_at' => now()->subDays(61)]);
    $oldTransaction = MonitoringTransaction::factory()->create(['started_at' => now()->subDays(181)]);
    MonitoringTransaction::factory()->attemptOf($oldTransaction)->create(['started_at' => now()->subDays(181)]);
    $linkedTransaction = MonitoringTransaction::factory()->create(['started_at' => now()->subDays(400)]);
    $incident = MonitoringIncident::factory()->create(['detected_at' => now()->subYears(3)]);
    MonitoringIncidentLink::query()->create(['monitoring_incident_id' => $incident->id, 'linkable_type' => 'transaction', 'linkable_id' => $linkedTransaction->id]);

    $oldResolvedError = MonitoringError::factory()->create(['status' => MonitoringErrorStatus::Resolved, 'last_seen_at' => now()->subDays(91)]);
    $oldOpenError = MonitoringError::factory()->create(['status' => MonitoringErrorStatus::New, 'last_seen_at' => now()->subDays(200)]);
    $oldDeployment = MonitoringDeployment::factory()->create(['created_at' => now()->subDays(731)]);

    $dryRun = app(MonitoringRetention::class)->run(dryRun: true);
    expect($dryRun['check_results_deleted'])->toBe(1)
        ->and(MonitoringCheckResult::query()->count())->toBe(2);

    $this->artisan('monitoring:prune')->assertSuccessful();
    $this->artisan('monitoring:prune')->assertSuccessful();

    expect(MonitoringCheckResult::query()->pluck('id')->all())->toBe([$freshCheck->id])
        ->and(MonitoringTransaction::query()->find($oldTransaction->id))->toBeNull()
        ->and(MonitoringTransaction::query()->where('parent_id', $oldTransaction->id)->count())->toBe(0)
        ->and(MonitoringTransaction::query()->find($linkedTransaction->id))->not->toBeNull()
        ->and($expiredPayload->refresh()->request_summary)->toBeNull()
        ->and($expiredPayload->payload_expired_at)->not->toBeNull()
        ->and($expiredPayload->status)->not->toBeNull()
        ->and(MonitoringError::query()->find($oldResolvedError->id))->toBeNull()
        ->and(MonitoringError::query()->find($oldOpenError->id))->not->toBeNull()
        ->and(MonitoringDeployment::query()->find($oldDeployment->id))->toBeNull()
        ->and(MonitoringIncident::query()->find($incident->id))->not->toBeNull();
});

it('aggregates only the metrics the dashboard uses and re-running overwrites the same rows', function () {
    $service = MonitoringService::factory()->create(['key' => 'database']);
    MonitoringCheckResult::factory()->count(3)->create(['monitoring_service_id' => $service->id, 'checked_at' => now()->subDay()->setTime(10, 0), 'response_time_ms' => 10]);
    MonitoringCheckResult::factory()->create(['monitoring_service_id' => $service->id, 'checked_at' => now()->subDay()->setTime(11, 0), 'status' => MonitoringServiceStatus::Down, 'response_time_ms' => 30]);
    MonitoringTransaction::factory()->create(['started_at' => now()->subDay()->setTime(9, 0), 'status' => 'unknown']);

    $this->artisan('monitoring:aggregate')->assertSuccessful();
    $this->artisan('monitoring:aggregate')->assertSuccessful();

    $check = MonitoringDailyAggregate::query()->where('metric', 'check')->sole();
    expect($check->total)->toBe(4)
        ->and($check->problems)->toBe(1)
        ->and($check->avg_response_ms)->toBe(15)
        ->and(MonitoringDailyAggregate::query()->where('metric', 'transaction')->sole()->problems)->toBe(1)
        ->and(app(MonitoringAggregator::class)->uptime(7)['database'])->toBe(75.0);
});
