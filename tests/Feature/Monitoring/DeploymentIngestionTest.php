<?php

use App\Enums\MonitoringDeploymentStatus;
use App\Models\MonitoringDeployment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function () {
    Cache::flush();
    config(['monitoring.deployments.ingestion_secret' => 'deploy-test-secret']);
});

function signedDeploymentHeaders(string $body, ?int $timestamp = null, ?string $nonce = null, string $secret = 'deploy-test-secret'): array
{
    $timestamp ??= now()->timestamp;
    $nonce ??= (string) Str::uuid();

    return [
        'X-Monitoring-Timestamp' => (string) $timestamp,
        'X-Monitoring-Nonce' => $nonce,
        'X-Monitoring-Signature' => hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$body, $secret),
        'Content-Type' => 'application/json',
    ];
}

function deploymentBody(array $overrides = []): string
{
    return json_encode(array_merge([
        'environment' => 'production', 'provider' => 'github_actions', 'run_id' => '9876', 'status' => 'succeeded', 'stage' => 'completed',
        'branch' => 'main', 'commit_sha' => '42ca827c1234567890abcdef1234567890abcdef', 'commit_message' => 'fix: reconcile units',
        'actor' => 'deploy-bot', 'migration_status' => 'ran', 'health_status' => 'healthy',
        'started_at' => now()->subMinutes(4)->toIso8601String(), 'finished_at' => now()->toIso8601String(),
        'metadata' => ['working_tree_dirty' => false, 'token' => 'should-never-be-stored', 'env' => 'APP_KEY=secret'],
    ], $overrides));
}

it('stores a correctly signed deployment without secrets', function () {
    $body = deploymentBody();

    $this->call('POST', '/monitoring/deployments', [], [], [], collect(signedDeploymentHeaders($body))->mapWithKeys(fn ($value, $key) => ['HTTP_'.strtoupper(str_replace('-', '_', $key)) => $value])->all(), $body)
        ->assertStatus(202);

    $deployment = MonitoringDeployment::query()->sole();
    expect($deployment->status)->toBe(MonitoringDeploymentStatus::Succeeded)
        ->and($deployment->duration_seconds)->toBeGreaterThanOrEqual(239)
        ->and($deployment->metadata)->toBe(['working_tree_dirty' => false])
        ->and(json_encode($deployment->getAttributes()))->not->toContain('should-never-be-stored')->not->toContain('APP_KEY');
});

function postDeployment($test, string $body, array $headers)
{
    return $test->call('POST', '/monitoring/deployments', [], [], [], collect($headers)->mapWithKeys(fn ($value, $key) => ['HTTP_'.strtoupper(str_replace('-', '_', $key)) => $value])->all(), $body);
}

it('rejects an invalid signature', function () {
    $body = deploymentBody();
    postDeployment($this, $body, signedDeploymentHeaders($body, secret: 'wrong-secret'))->assertStatus(401);
    expect(MonitoringDeployment::query()->count())->toBe(0);
});

it('rejects an expired timestamp', function () {
    $body = deploymentBody();
    postDeployment($this, $body, signedDeploymentHeaders($body, now()->subMinutes(10)->timestamp))->assertStatus(401);
});

it('rejects a replayed nonce', function () {
    $body = deploymentBody();
    $headers = signedDeploymentHeaders($body);

    postDeployment($this, $body, $headers)->assertStatus(202);
    postDeployment($this, $body, $headers)->assertStatus(409);
});

it('rejects an invalid environment or status', function (array $override) {
    $body = deploymentBody($override);
    postDeployment($this, $body, signedDeploymentHeaders($body))->assertStatus(422);
})->with([
    'environment with spaces' => [['environment' => 'prod uction']],
    'unknown status' => [['status' => 'rolled_back']],
    'non-hex commit' => [['commit_sha' => 'not-a-sha']],
]);

it('is not configured (404) without a server secret', function () {
    config(['monitoring.deployments.ingestion_secret' => null]);
    $body = deploymentBody();

    postDeployment($this, $body, signedDeploymentHeaders($body))->assertNotFound();
});

it('updates the same run through stages from the local command', function () {
    $this->artisan('monitoring:record-deployment', ['--run-id' => 'vps-1', '--status' => 'started', '--stage' => 'migrate', '--commit' => 'abc1234'])->assertSuccessful();
    $this->artisan('monitoring:record-deployment', ['--run-id' => 'vps-1', '--status' => 'succeeded', '--stage' => 'completed', '--health-status' => 'healthy'])->assertSuccessful();

    $deployment = MonitoringDeployment::query()->sole();
    expect($deployment->status)->toBe(MonitoringDeploymentStatus::Succeeded)
        ->and($deployment->stage)->toBe('completed')
        ->and($deployment->commit_sha)->toBe('abc1234')
        ->and($deployment->health_status)->toBe('healthy');
});
