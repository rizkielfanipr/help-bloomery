<?php

use App\Enums\MonitoringErrorStatus;
use App\Enums\MonitoringIncidentStatus;
use App\Enums\MonitoringServiceStatus;
use App\Enums\MonitoringTransactionStatus;
use App\Filament\Helpdesk\Pages\Monitoring\MonitoringOverviewPage;
use App\Filament\Helpdesk\Pages\Monitoring\MonitoringQueueSchedulerPage;
use App\Filament\Helpdesk\Pages\Monitoring\MonitoringServiceHealthPage;
use App\Filament\Helpdesk\Pages\Monitoring\MonitoringSettingsPage;
use App\Filament\Helpdesk\Resources\MonitoringDeployments\MonitoringDeploymentResource;
use App\Filament\Helpdesk\Resources\MonitoringErrors\MonitoringErrorResource;
use App\Filament\Helpdesk\Resources\MonitoringErrors\Pages\ViewMonitoringError;
use App\Filament\Helpdesk\Resources\MonitoringIncidents\MonitoringIncidentResource;
use App\Filament\Helpdesk\Resources\MonitoringIncidents\Pages\ListMonitoringIncidents;
use App\Filament\Helpdesk\Resources\MonitoringIncidents\Pages\ViewMonitoringIncident;
use App\Filament\Helpdesk\Resources\MonitoringTransactions\Pages\ListMonitoringTransactions;
use App\Models\MonitoringAlert;
use App\Models\MonitoringCheckResult;
use App\Models\MonitoringDeployment;
use App\Models\MonitoringError;
use App\Models\MonitoringIncident;
use App\Models\MonitoringService;
use App\Models\MonitoringSetting;
use App\Models\MonitoringTransaction;
use App\Models\User;
use App\Services\Monitoring\MonitoringHeartbeats;
use App\Services\Monitoring\MonitoringOverview;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
});

function pageUser(array $permissions): User
{
    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $user->givePermissionTo(['access backoffice', ...$permissions]);

    return $user;
}

function monitoringUrls(): array
{
    return [
        'overview' => MonitoringOverviewPage::getUrl(),
        'health' => MonitoringServiceHealthPage::getUrl(),
        'queue' => MonitoringQueueSchedulerPage::getUrl(),
        'settings' => MonitoringSettingsPage::getUrl(),
        'errors' => MonitoringErrorResource::getUrl('index'),
        'deployments' => MonitoringDeploymentResource::getUrl('index'),
        'incidents' => MonitoringIncidentResource::getUrl('index'),
    ];
}

it('enforces the server-side permission matrix on every monitoring route', function (array $permissions, array $allowed) {
    $this->actingAs(pageUser($permissions));

    foreach (monitoringUrls() as $page => $url) {
        $this->get($url)->assertStatus(in_array($page, $allowed, true) ? 200 : 403);
    }
})->with([
    'operational user' => [[], []],
    'management (overview only)' => [['view system monitoring'], ['overview', 'health', 'incidents']],
    'IT technical' => [['view system monitoring', 'view monitoring technical details'], ['overview', 'health', 'queue', 'errors', 'deployments', 'incidents']],
    'settings manager' => [['view system monitoring', 'manage monitoring settings'], ['overview', 'health', 'incidents', 'settings']],
]);

it('renders the overview from local snapshots only without any HTTP call', function () {
    Http::fake();
    MonitoringService::factory()->down()->create(['key' => 'esb_core', 'name' => 'ESB Auth & Master Product', 'is_critical' => true]);
    MonitoringService::factory()->healthy()->create(['key' => 'database', 'name' => 'Database']);
    MonitoringTransaction::factory()->status(MonitoringTransactionStatus::Unknown)->create();
    MonitoringIncident::factory()->create(['title' => 'Gangguan ESB pagi']);
    MonitoringAlert::factory()->create(['title' => 'ESB Auth & Master Product Down']);
    app(MonitoringHeartbeats::class)->beat(MonitoringHeartbeats::SCHEDULER);
    $this->actingAs(pageUser(['view system monitoring']));

    $this->get(MonitoringOverviewPage::getUrl())
        ->assertOk()
        ->assertSee('Kesehatan Sistem')
        ->assertSee('ESB Auth &amp; Master Product', false)
        ->assertSee('Down')
        ->assertSee('1 unknown')
        ->assertSee('Gangguan ESB pagi')
        ->assertSee('Tepat waktu')
        ->assertSee('Not Configured')
        ->assertDontSee('ESB Transactions');

    Http::assertNothingSent();
});

it('marks a stale service snapshot as unknown instead of trusting the old status', function () {
    MonitoringService::factory()->healthy()->create(['key' => 'database', 'name' => 'Database', 'last_checked_at' => now()->subHour()]);
    $this->actingAs(pageUser(['view system monitoring']));

    $this->get(MonitoringOverviewPage::getUrl())->assertSee('Unknown (data lama)');
});

it('lets alert handlers acknowledge an alert but not management', function () {
    $alert = MonitoringAlert::factory()->create();

    $this->actingAs(pageUser(['view system monitoring']));
    Livewire::test(MonitoringOverviewPage::class)->call('acknowledgeAlert', $alert->id)->assertForbidden();

    $handler = pageUser(['view system monitoring', 'acknowledge monitoring alerts']);
    $this->actingAs($handler);
    Livewire::test(MonitoringOverviewPage::class)->call('acknowledgeAlert', $alert->id)->assertHasNoErrors();

    expect($alert->refresh()->acknowledged_by)->toBe($handler->id);
});

it('shows check history on the service health page', function () {
    $service = MonitoringService::factory()->healthy()->create(['key' => 'database', 'name' => 'Database']);
    $service->checkResults()->create(['status' => MonitoringServiceStatus::Down, 'checked_at' => now()->subMinutes(5), 'safe_message' => 'Query database ringan gagal.']);
    $this->actingAs(pageUser(['view system monitoring']));

    Livewire::test(MonitoringServiceHealthPage::class)
        ->assertSee('Database')
        ->call('selectService', 'database')
        ->assertSee('Query database ringan gagal.');
});

it('shows heartbeats and scheduled tasks read-only on the queue page', function () {
    $this->actingAs(pageUser(['view system monitoring', 'view monitoring technical details']));

    $this->get(MonitoringQueueSchedulerPage::getUrl())
        ->assertOk()
        ->assertSee('monitoring:run-checks')
        ->assertSee('Belum ada riwayat')
        ->assertDontSee('Retry');
});

it('validates settings ranges and saves overrides with the actor', function () {
    $manager = pageUser(['view system monitoring', 'manage monitoring settings']);
    $this->actingAs($manager);

    Livewire::test(MonitoringSettingsPage::class)
        ->set('data.alerts_reminder_minutes', 1)
        ->call('save')
        ->assertHasErrors(['data.alerts_reminder_minutes']);

    $component = Livewire::test(MonitoringSettingsPage::class);
    $services = $component->get('data.services');
    $firstKey = array_key_first($services);
    $component->set("data.services.{$firstKey}.timeout_seconds", 30)
        ->set("data.services.{$firstKey}.check_interval_seconds", 60)
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(MonitoringSettingsPage::class)
        ->set('data.retention_payload_days', 14)
        ->set('data.maintenance_starts_at', now()->toDateTimeString())
        ->set('data.maintenance_ends_at', now()->addHour()->toDateTimeString())
        ->set('data.maintenance_reason', 'Upgrade server')
        ->call('save')
        ->assertHasNoErrors();

    expect(MonitoringSetting::query()->where('key', 'retention.payload_days')->sole()->value)->toBe(['v' => 14])
        ->and(MonitoringSetting::query()->where('key', 'retention.payload_days')->sole()->updated_by)->toBe($manager->id)
        ->and(MonitoringSetting::query()->where('key', 'maintenance.window')->sole()->value['v']['reason'])->toBe('Upgrade server');
});

it('rejects check timeouts and intervals outside the validated ranges', function () {
    $this->actingAs(pageUser(['view system monitoring', 'manage monitoring settings']));
    $component = Livewire::test(MonitoringSettingsPage::class);
    $firstKey = array_key_first($component->get('data.services'));

    $component->set("data.services.{$firstKey}.timeout_seconds", 45)
        ->set("data.services.{$firstKey}.check_interval_seconds", 30)
        ->call('save')
        ->assertHasErrors(["data.services.{$firstKey}.timeout_seconds", "data.services.{$firstKey}.check_interval_seconds"]);

    expect(MonitoringService::query()->where('key', $component->get("data.services.{$firstKey}.key"))->sole()->timeout_seconds)->toBeLessThanOrEqual(30);
});

it('creates and transitions an incident through the Incident Center UI', function () {
    $this->actingAs(pageUser(['view system monitoring', 'manage monitoring incidents']));

    Livewire::test(ListMonitoringIncidents::class)
        ->callAction('createIncident', ['title' => 'Receiving lambat', 'severity' => 'sev3', 'summary' => 'GR lambat'])
        ->assertHasNoActionErrors();
    $incident = MonitoringIncident::query()->sole();

    Livewire::test(ViewMonitoringIncident::class, ['record' => $incident->uuid])
        ->assertSee('Receiving lambat')
        ->callAction('transition_acknowledged', ['note' => 'Ditangani IT'])
        ->callAction('transition_investigating')
        ->callAction('transition_resolved', ['resolution' => 'Index ditambahkan'])
        ->assertHasNoActionErrors();

    expect($incident->refresh()->status)->toBe(MonitoringIncidentStatus::Resolved)
        ->and($incident->events()->count())->toBe(4);
});

it('hides incident management actions from read-only management users', function () {
    $incident = MonitoringIncident::factory()->create();
    $this->actingAs(pageUser(['view system monitoring']));

    Livewire::test(ListMonitoringIncidents::class)->assertActionHidden('createIncident');
    Livewire::test(ViewMonitoringIncident::class, ['record' => $incident->uuid])->assertActionHidden('transition_acknowledged');
});

it('triages an error and requires a reason to ignore it', function () {
    $error = MonitoringError::factory()->create();
    $this->actingAs(pageUser(['view system monitoring', 'view monitoring technical details', 'acknowledge monitoring alerts']));

    Livewire::test(ViewMonitoringError::class, ['record' => $error->uuid])
        ->callAction('triage', ['status' => 'ignored', 'note' => ''])
        ->assertHasActionErrors(['note' => 'required']);

    Livewire::test(ViewMonitoringError::class, ['record' => $error->uuid])
        ->callAction('triage', ['status' => 'ignored', 'note' => 'Noise dari bot crawler'])
        ->assertHasNoActionErrors();

    expect($error->refresh()->status)->toBe(MonitoringErrorStatus::Ignored)
        ->and($error->triage_note)->toBe('Noise dari bot crawler');
});

it('lists deployments read-only and explains when ingestion is not configured', function () {
    $this->actingAs(pageUser(['view system monitoring', 'view monitoring technical details']));
    $this->get(MonitoringDeploymentResource::getUrl('index'))->assertOk()->assertSee('Not Configured');

    $deployment = MonitoringDeployment::factory()->create(['commit_sha' => '42ca827c0000000000000000000000000000aaaa']);
    $this->get(MonitoringDeploymentResource::getUrl('view', ['record' => $deployment]))->assertOk()->assertSee('42ca827c');
});

it('shows the System Monitoring sidebar group only to permitted users', function () {
    $this->actingAs(pageUser(['view system monitoring']));
    $this->get(MonitoringOverviewPage::getUrl())->assertSee('System Monitoring')->assertSee('Service Health')->assertDontSee('Deployments');

    $this->actingAs(pageUser(['view system monitoring', 'view monitoring technical details']));
    $this->get(MonitoringOverviewPage::getUrl())->assertSee('Deployments')->assertDontSee('Settings');
});

it('builds the overview snapshot with a bounded number of queries regardless of data volume', function () {
    $services = MonitoringService::factory()->count(12)->healthy()->create();
    foreach ($services as $service) {
        MonitoringCheckResult::factory()->count(30)->create(['monitoring_service_id' => $service->id, 'checked_at' => now()->subMinutes(rand(1, 1400))]);
    }
    MonitoringTransaction::factory()->count(200)->create();
    MonitoringError::factory()->count(50)->create();

    DB::enableQueryLog();
    app(MonitoringOverview::class)->snapshot();
    $queries = count(DB::getQueryLog());

    expect($queries)->toBeLessThanOrEqual(16);
});

it('loads the transaction index page with a bounded number of queries', function () {
    $parents = MonitoringTransaction::factory()->count(60)->create(['actor_id' => User::factory()]);
    $parents->each(fn ($parent) => MonitoringTransaction::factory()->attemptOf($parent)->create());
    $this->actingAs(pageUser(['view system monitoring', 'view monitoring technical details']));

    DB::enableQueryLog();
    Livewire::test(ListMonitoringTransactions::class)->assertCountTableRecords(60);
    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries->filter(fn (string $sql): bool => str_contains($sql, 'from "users"') && str_contains($sql, '"id" in'))->count())->toBeLessThanOrEqual(2)
        ->and($queries->filter(fn (string $sql): bool => str_contains($sql, 'request_summary'))->count())->toBe(0);
});
