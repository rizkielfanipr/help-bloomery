<?php

namespace App\Http\Controllers;

use App\Actions\Monitoring\RecordMonitoringDeploymentAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Signed deployment callback (docs/products/system-monitoring/prd.md §14). Disabled (404) until
 * MONITORING_DEPLOYMENT_SECRET is configured on the server.
 *
 * Signature: hex HMAC-SHA256 of "{timestamp}.{nonce}.{raw body}" with the shared secret, sent as
 * X-Monitoring-Signature together with X-Monitoring-Timestamp (unix seconds) and
 * X-Monitoring-Nonce. Old timestamps and reused nonces are rejected.
 */
class MonitoringDeploymentIngestionController extends Controller
{
    public function __invoke(Request $request, RecordMonitoringDeploymentAction $record): JsonResponse
    {
        $secret = (string) config('monitoring.deployments.ingestion_secret', '');
        abort_if($secret === '', 404);

        $timestamp = (string) $request->header('X-Monitoring-Timestamp', '');
        $nonce = (string) $request->header('X-Monitoring-Nonce', '');
        $signature = (string) $request->header('X-Monitoring-Signature', '');
        $tolerance = (int) config('monitoring.deployments.timestamp_tolerance_seconds', 300);

        if (! ctype_digit($timestamp) || abs(now()->timestamp - (int) $timestamp) > $tolerance) {
            return response()->json(['message' => 'Timestamp tidak valid atau kedaluwarsa.'], 401);
        }

        if (preg_match('/^[A-Za-z0-9-]{16,64}$/', $nonce) !== 1) {
            return response()->json(['message' => 'Nonce tidak valid.'], 401);
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$request->getContent(), $secret);
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Signature tidak valid.'], 401);
        }

        if (! Cache::add('monitoring:deployment-nonce:'.$nonce, true, $tolerance * 2)) {
            return response()->json(['message' => 'Nonce sudah digunakan.'], 409);
        }

        try {
            $deployment = $record->execute((array) $request->json()->all());
        } catch (ValidationException $exception) {
            return response()->json(['message' => 'Payload deployment tidak valid.', 'errors' => $exception->errors()], 422);
        }

        return response()->json(['uuid' => $deployment->uuid, 'status' => $deployment->status->value], 202);
    }
}
