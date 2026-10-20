<?php

use App\Enums\MonitoringServiceStatus;
use App\Jobs\Monitoring\RecordQueueHeartbeatJob;
use App\Models\MonitoringAlert;
use App\Models\MonitoringCheckResult;
use App\Models\MonitoringHeartbeat;
use App\Models\MonitoringService;
use App\Models\User;
use App\Notifications\MonitoringAlertNotification;
use App\Services\Monitoring\HealthCheckRunner;
use App\Services\Monitoring\MonitoringHeartbeats;
use App\Services\Monitoring\MonitoringServiceCatalog;
use App\Services\Monitoring\MonitoringSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Cache::flush();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->itUser = User::factory()->create(['is_active' => true]);
    $this->itUser->assignRole('IT_STAFF');
    config([
        'app.url' => 'https://help-bloomery.test',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'monitor-user',
        'esb.core.password' => 'monitor-secret',
    ]);
});

function serviceFor(string $key): MonitoringService
{
    return app(MonitoringServiceCatalog::class)->sync()->firstWhere('key', $key)->refresh();
}

it('creates one service row per configured check and keeps edited configuration', function () {
    app(MonitoringServiceCatalog::class)->sync();
    MonitoringService::query()->where('key', 'database')->update(['check_interval_seconds' => 300]);

    $services = app(MonitoringServiceCatalog::class)->sync();

    expect($services->pluck('key')->all())->toContain('application', 'database', 'cache', 'queue', 'scheduler', 'object_storage', 'esb_core')
        ->and(MonitoringService::query()->where('key', 'database')->sole()->check_interval_seconds)->toBe(300)
        ->and(MonitoringService::query()->count())->toBe(count(config('monitoring.checks')));
});

it('stores database and cache snapshots as healthy', function () {
    $runner = app(HealthCheckRunner::class);

    expect($runner->run(serviceFor('database')))->toBe(MonitoringServiceStatus::Healthy)
        ->and($runner->run(serviceFor('cache')))->toBe(MonitoringServiceStatus::Healthy)
        ->and(MonitoringCheckResult::query()->count())->toBe(2)
        ->and(Cache::get('monitoring:probe:anything'))->toBeNull();
});

it('probes object storage without leaving a file behind', function () {
    Storage::fake('b2');
    config(['filesystems.disks.b2' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/b2')]]);
    Storage::fake('b2');

    expect(app(HealthCheckRunner::class)->run(serviceFor('object_storage')))->toBe(MonitoringServiceStatus::Healthy)
        ->and(Storage::disk('b2')->allFiles())->toBe([]);
});

it('reports an unconfigured ESB check as unknown without calling ESB', function () {
    config(['esb.core.username' => null]);
    Http::fake();

    expect(app(HealthCheckRunner::class)->run(serviceFor('esb_core')))->toBe(MonitoringServiceStatus::Unknown);
    Http::assertNothingSent();
    expect(serviceFor('esb_core')->last_safe_message)->toContain('belum dikonfigurasi');
});

it('uses a read-only ESB probe and never mutates', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/list*' => Http::response(['status' => 'ok', 'result' => ['data' => []]]),
    ]);

    expect(app(HealthCheckRunner::class)->run(serviceFor('esb_core')))->toBe(MonitoringServiceStatus::Healthy);
    Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET' && ! str_ends_with($request->url(), '/auth/login'));
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && $request['limit'] === 1);
});

it('opens one alert only after the failure threshold and does not spam while the outage continues', function () {
    Notification::fake();
    Http::fake(['https://core-esb.test/*' => Http::failedConnection('cURL error 28: Operation timed out')]);
    $service = serviceFor('esb_core');
    $runner = app(HealthCheckRunner::class);

    $runner->run($service);
    expect(MonitoringAlert::query()->count())->toBe(0)
        ->and($service->refresh()->last_status)->toBe(MonitoringServiceStatus::Degraded);

    foreach (range(1, 4) as $ignored) {
        $runner->run($service->refresh());
    }

    expect($service->refresh()->last_status)->toBe(MonitoringServiceStatus::Down)
        ->and(MonitoringAlert::query()->count())->toBe(1)
        ->and(MonitoringAlert::query()->active()->count())->toBe(1);
    Notification::assertSentToTimes($this->itUser, MonitoringAlertNotification::class, 1);
});

it('resolves the alert after the recovery threshold and records the outage duration', function () {
    Notification::fake();
    $failing = true;
    Http::fake(function (Request $request) use (&$failing) {
        return $failing && ! str_ends_with($request->url(), '/auth/login')
            ? Http::response(['status' => 'error'], 503)
            : Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token', 'data' => []]]);
    });
    $service = serviceFor('esb_core');
    $runner = app(HealthCheckRunner::class);
    $runner->run($service);
    $runner->run($service->refresh());
    expect(MonitoringAlert::query()->active()->count())->toBe(1);

    $this->travel(10)->minutes();
    $failing = false;
    $runner->run($service->refresh());
    expect(MonitoringAlert::query()->active()->count())->toBe(1);
    $runner->run($service->refresh());

    $alert = MonitoringAlert::query()->sole();
    expect($alert->active_dedup_key)->toBeNull()
        ->and($alert->duration_seconds)->toBeGreaterThanOrEqual(600)
        ->and($service->refresh()->last_status)->toBe(MonitoringServiceStatus::Healthy);
    Notification::assertSentToTimes($this->itUser, MonitoringAlertNotification::class, 2);
    Notification::assertSentTo($this->itUser, MonitoringAlertNotification::class, fn (MonitoringAlertNotification $notification): bool => $notification->event === 'recovered');
});

it('keeps recording but holds alerts during a maintenance window', function () {
    Notification::fake();
    Http::fake(['https://core-esb.test/*' => Http::response([], 503)]);
    app(MonitoringSettings::class)->put(['maintenance.window' => [
        'starts_at' => now()->subMinute()->toDateTimeString(), 'ends_at' => now()->addHour()->toDateTimeString(), 'reason' => 'Upgrade ESB', 'service_keys' => [],
    ]], null);
    $service = serviceFor('esb_core');

    foreach (range(1, 3) as $ignored) {
        app(HealthCheckRunner::class)->run($service->refresh());
    }

    expect($service->refresh()->last_status)->toBe(MonitoringServiceStatus::Maintenance)
        ->and($service->last_observed_status)->toBe(MonitoringServiceStatus::Down)
        ->and(MonitoringCheckResult::query()->count())->toBe(3)
        ->and(MonitoringAlert::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('distinguishes healthy, late, and unknown queue heartbeats', function () {
    $runner = app(HealthCheckRunner::class);
    expect($runner->run(serviceFor('queue')))->toBe(MonitoringServiceStatus::Unknown);

    (new RecordQueueHeartbeatJob(now()->toIso8601String()))->handle(app(MonitoringHeartbeats::class));
    expect($runner->run(serviceFor('queue')))->toBe(MonitoringServiceStatus::Healthy);

    $this->travel(20)->minutes();
    $service = serviceFor('queue');
    $service->update(['failure_threshold' => 1]);
    expect($runner->run($service->refresh()))->toBe(MonitoringServiceStatus::Down);
});

it('detects a late scheduler heartbeat from the gap before the latest beat', function () {
    app(MonitoringHeartbeats::class)->beat(MonitoringHeartbeats::SCHEDULER);
    $this->travel(10)->minutes();
    app(MonitoringHeartbeats::class)->beat(MonitoringHeartbeats::SCHEDULER);

    $service = serviceFor('scheduler');
    $service->update(['failure_threshold' => 1]);

    expect(app(HealthCheckRunner::class)->run($service->refresh()))->toBe(MonitoringServiceStatus::Degraded)
        ->and(MonitoringHeartbeat::query()->where('key', 'scheduler')->sole()->metadata['previous_beat_at'])->not->toBeNull();
});

it('runs the scheduled command: heartbeats, queue job dispatch, and due checks only', function () {
    Bus::fake([RecordQueueHeartbeatJob::class]);
    Http::fake();
    MonitoringService::query()->delete();
    config(['monitoring.checks' => array_intersect_key(config('monitoring.checks'), array_flip(['database', 'cache']))]);

    $this->artisan('monitoring:run-checks')->assertSuccessful();
    $this->artisan('monitoring:run-checks')->assertSuccessful();

    Bus::assertDispatchedTimes(RecordQueueHeartbeatJob::class, 2);
    expect(MonitoringHeartbeat::query()->where('key', 'scheduler')->exists())->toBeTrue()
        ->and(MonitoringCheckResult::query()->count())->toBe(2);
    Http::assertNothingSent();
});

it('backs off external checks while an ESB outage continues', function () {
    $service = serviceFor('esb_core');
    $service->forceFill(['last_checked_at' => now()->subSeconds(320), 'consecutive_failures' => 8, 'failure_threshold' => 2])->save();

    expect(app(HealthCheckRunner::class)->isDue($service))->toBeFalse();

    $service->forceFill(['last_checked_at' => now()->subSeconds(1300)])->save();
    expect(app(HealthCheckRunner::class)->isDue($service))->toBeTrue();
});

it('protects the run-checks schedule against overlap', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'monitoring:run-checks'));

    expect($event)->not->toBeNull()
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expression)->toBe('* * * * *');
});
