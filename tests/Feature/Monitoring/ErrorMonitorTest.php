<?php

use App\Enums\MonitoringErrorStatus;
use App\Models\MonitoringAlert;
use App\Models\MonitoringError;
use App\Models\User;
use App\Services\Monitoring\ErrorRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    Cache::flush();
});

it('fingerprints identical errors into one row with an occurrence count', function () {
    $recorder = app(ErrorRecorder::class);
    config(['monitoring.errors.throttle_seconds' => 1]);

    foreach (range(1, 3) as $index) {
        $this->travel(2)->seconds();
        $recorder->record(new RuntimeException("Gagal memproses order {$index} untuk budi@bloomery.test"));
    }

    $error = MonitoringError::query()->sole();
    expect($error->occurrence_count)->toBe(3)
        ->and($error->status)->toBe(MonitoringErrorStatus::New)
        ->and($error->safe_message)->not->toContain('budi@bloomery.test')
        ->and($error->metadata['frames'])->not->toBeEmpty();
});

it('produces a deterministic fingerprint for messages that only differ by identifiers', function () {
    $recorder = app(ErrorRecorder::class);

    expect($recorder->normalizeMessage('Order 123 failed for "abc"'))->toBe($recorder->normalizeMessage('Order 987 failed for "xyz"'))
        ->and($recorder->fingerprint(['A', 'b']))->toBe($recorder->fingerprint(['A', 'b']))
        ->and($recorder->fingerprint(['A', 'b']))->not->toBe($recorder->fingerprint(['A', 'c']));
});

it('throttles writes and flushes buffered occurrences on the next write', function () {
    $recorder = app(ErrorRecorder::class);
    config(['monitoring.errors.throttle_seconds' => 60]);
    $exception = fn () => new RuntimeException('Throttled failure');

    foreach (range(1, 5) as $ignored) {
        $recorder->record($exception());
    }
    expect(MonitoringError::query()->sole()->occurrence_count)->toBe(1);

    $this->travel(61)->seconds();
    $recorder->record($exception());

    expect(MonitoringError::query()->sole()->occurrence_count)->toBe(6);
});

it('ignores validation, authorization, and 4xx noise', function () {
    $recorder = app(ErrorRecorder::class);

    $recorder->record(ValidationException::withMessages(['x' => 'invalid']));
    $recorder->record(new AuthorizationException);
    $recorder->record(new NotFoundHttpException);
    $recorder->record(new HttpException(419));

    expect(MonitoringError::query()->count())->toBe(0);
});

it('marks a resolved error that happens again as a regression without losing its history', function () {
    $recorder = app(ErrorRecorder::class);
    config(['monitoring.errors.throttle_seconds' => 1]);
    $recorder->record(new LogicException('Regressed'));
    MonitoringError::query()->update(['status' => MonitoringErrorStatus::Resolved->value]);

    $this->travel(2)->seconds();
    $recorder->record(new LogicException('Regressed'));

    $error = MonitoringError::query()->sole();
    expect($error->status)->toBe(MonitoringErrorStatus::New)
        ->and($error->is_regression)->toBeTrue()
        ->and($error->occurrence_count)->toBe(2);
});

it('does not change the user-facing error response and records the failing route', function () {
    Route::get('/monitoring-test-crash', fn () => throw new RuntimeException('Boom monitoring'))->middleware('web')->name('monitoring.test.crash');

    $this->get('/monitoring-test-crash')->assertStatus(500);

    $error = MonitoringError::query()->sole();
    expect($error->route_name)->toBe('monitoring.test.crash')
        ->and($error->exception_class)->toBe(RuntimeException::class)
        ->and($error->last_correlation_id)->not->toBeNull();
});

it('only alerts on new error fingerprints when error alerts are enabled', function () {
    app(ErrorRecorder::class)->record(new RuntimeException('Quiet by default'));
    expect(MonitoringAlert::query()->count())->toBe(0);

    config(['monitoring.alerts.errors' => true]);
    app(ErrorRecorder::class)->record(new RuntimeException('Loud when enabled'));
    expect(MonitoringAlert::query()->where('source_type', 'error')->count())->toBe(1);
});

it('never lets a broken error store interfere with reporting', function () {
    Schema::drop('monitoring_errors');

    app(ErrorRecorder::class)->record(new RuntimeException('Store missing'));

    expect(true)->toBeTrue();
});

it('keeps the browser error endpoint disabled until enabled', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('/monitoring/browser-errors', ['message' => 'x', 'page' => '/'])->assertNotFound();
});

it('records an authenticated browser error with only the allowlisted fields', function () {
    config(['monitoring.errors.browser_enabled' => true]);
    $this->postJson('/monitoring/browser-errors', ['message' => 'x', 'page' => '/'])->assertUnauthorized();

    $this->actingAs(User::factory()->create());
    $this->postJson('/monitoring/browser-errors', [
        'message' => "Cannot read properties of undefined (reading 'qty')",
        'page' => 'https://help-bloomery.test/stock-cards/12?token=secret',
        'source' => 'https://help-bloomery.test/build/assets/helpdesk-abc.js?v=1',
        'line' => 42,
        'browser' => 'Mozilla/5.0',
        'form' => ['password' => 'hunter2'],
    ])->assertStatus(202);

    $error = MonitoringError::query()->sole();
    expect($error->source)->toBe('browser')
        ->and($error->route_name)->toBe('/stock-cards/12')
        ->and($error->action)->toBe('helpdesk-abc.js:42')
        ->and(json_encode($error->getAttributes()))->not->toContain('hunter2')->not->toContain('token=secret');
});
