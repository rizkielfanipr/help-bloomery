<?php

use App\Actions\Monitoring\AddMonitoringIncidentNoteAction;
use App\Actions\Monitoring\AssignMonitoringIncidentOwnerAction;
use App\Actions\Monitoring\CreateMonitoringIncidentAction;
use App\Actions\Monitoring\LinkMonitoringIncidentAction;
use App\Actions\Monitoring\TransitionMonitoringIncidentAction;
use App\Actions\Monitoring\UpdateMonitoringIncidentAnalysisAction;
use App\Enums\MonitoringIncidentStatus;
use App\Enums\MonitoringServiceStatus;
use App\Enums\MonitoringTransactionStatus;
use App\Models\ErpRepairRequest;
use App\Models\MonitoringIncident;
use App\Models\MonitoringIncidentEvent;
use App\Models\MonitoringService;
use App\Models\MonitoringTransaction;
use App\Models\User;
use App\Services\Monitoring\AlertManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->assignRole('IT_STAFF');
});

function newIncident(User $actor): MonitoringIncident
{
    return app(CreateMonitoringIncidentAction::class)->execute(['title' => 'ESB timeout massal', 'severity' => 'sev2', 'affected_services' => ['esb_core']], $actor);
}

it('creates an incident with a number and an append-only created event', function () {
    $incident = newIncident($this->manager);

    expect($incident->number)->toStartWith('INC-')
        ->and($incident->status)->toBe(MonitoringIncidentStatus::Open)
        ->and($incident->events()->sole()->event_type)->toBe('created');
});

it('walks the full lifecycle and records every transition on the timeline', function () {
    $incident = newIncident($this->manager);
    $transition = app(TransitionMonitoringIncidentAction::class);

    $transition->execute($incident, MonitoringIncidentStatus::Acknowledged, $this->manager);
    $transition->execute($incident->refresh(), MonitoringIncidentStatus::Investigating, $this->manager);
    $transition->execute($incident->refresh(), MonitoringIncidentStatus::Monitoring, $this->manager);
    $transition->execute($incident->refresh(), MonitoringIncidentStatus::Resolved, $this->manager, ['resolution' => 'Token ESB diperbarui', 'root_cause' => 'Session ESB tertutup']);
    $transition->execute($incident->refresh(), MonitoringIncidentStatus::Open, $this->manager, ['note' => 'Timeout terjadi lagi']);

    $incident->refresh();
    expect($incident->status)->toBe(MonitoringIncidentStatus::Open)
        ->and($incident->reopened_count)->toBe(1)
        ->and($incident->acknowledged_at)->not->toBeNull()
        ->and($incident->resolved_at)->toBeNull()
        ->and($incident->events->pluck('to_status')->filter()->values()->all())->toBe(['open', 'acknowledged', 'investigating', 'monitoring', 'resolved', 'open']);
});

it('rejects invalid transitions and a resolution without explanation', function () {
    $incident = newIncident($this->manager);

    expect(fn () => app(TransitionMonitoringIncidentAction::class)->execute($incident, MonitoringIncidentStatus::Monitoring, $this->manager))
        ->toThrow(ValidationException::class);
    expect(fn () => app(TransitionMonitoringIncidentAction::class)->execute($incident, MonitoringIncidentStatus::Resolved, $this->manager))
        ->toThrow(ValidationException::class);
});

it('keeps timeline events append-only', function () {
    $event = newIncident($this->manager)->events()->sole();

    expect(fn () => $event->update(['note' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class)
        ->and(MonitoringIncidentEvent::query()->count())->toBe(1);
});

it('refuses incident management without the permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('view system monitoring');

    expect(fn () => newIncident($viewer))->toThrow(AuthorizationException::class);
});

it('assigns an owner, adds notes, and links records without changing a business transaction', function () {
    $incident = newIncident($this->manager);
    $transaction = MonitoringTransaction::factory()->status(MonitoringTransactionStatus::Unknown)->create();
    $ticket = ErpRepairRequest::factory()->create();

    app(AssignMonitoringIncidentOwnerAction::class)->execute($incident, $this->manager, $this->manager);
    app(AddMonitoringIncidentNoteAction::class)->execute($incident, 'Cek token di user@bloomery.test', $this->manager);
    $link = app(LinkMonitoringIncidentAction::class)->link($incident, 'transaction', $transaction->uuid, $this->manager);
    app(LinkMonitoringIncidentAction::class)->link($incident, 'transaction', $transaction->uuid, $this->manager);
    app(UpdateMonitoringIncidentAnalysisAction::class)->execute($incident, ['erp_repair_request_id' => $ticket->id], $this->manager);

    expect($incident->refresh()->owner_id)->toBe($this->manager->id)
        ->and($incident->erp_repair_request_id)->toBe($ticket->id)
        ->and($incident->links()->count())->toBe(1)
        ->and($transaction->refresh()->status)->toBe(MonitoringTransactionStatus::Unknown)
        ->and($incident->events->pluck('event_type')->all())->toBe(['created', 'owner_assigned', 'note', 'linked', 'analysis_updated'])
        ->and($incident->events->firstWhere('event_type', 'note')->note)->not->toContain('user@bloomery.test');

    app(LinkMonitoringIncidentAction::class)->unlink($incident, $link, $this->manager);
    expect($incident->links()->count())->toBe(0)
        ->and($incident->events()->where('event_type', 'unlinked')->count())->toBe(1);
});

it('creates an incident automatically only when enabled for SEV-1/SEV-2 alerts', function () {
    config(['monitoring.alerts.auto_incident' => true]);
    $service = MonitoringService::factory()->create(['key' => 'database', 'is_critical' => true, 'failure_threshold' => 1]);
    $service->forceFill(['consecutive_failures' => 1, 'last_safe_message' => 'Query database ringan gagal.'])->save();

    app(AlertManager::class)->evaluateService($service, MonitoringServiceStatus::Healthy, MonitoringServiceStatus::Down);

    $incident = MonitoringIncident::query()->sole();
    expect($incident->is_automatic)->toBeTrue()
        ->and($incident->links()->sole()->linkable_type)->toBe('alert');
});
