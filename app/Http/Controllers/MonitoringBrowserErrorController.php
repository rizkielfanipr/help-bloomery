<?php

namespace App\Http\Controllers;

use App\Services\Monitoring\ErrorRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Opt-in browser error intake (docs/operations/monitoring/prd.md §13.2): authenticated web session,
 * CSRF-protected, rate-limited, and accepting only a small allowlisted payload.
 */
class MonitoringBrowserErrorController extends Controller
{
    public function __invoke(Request $request, ErrorRecorder $recorder): JsonResponse
    {
        abort_unless((bool) config('monitoring.errors.browser_enabled', false), 404);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:300'],
            'page' => ['required', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:200'],
            'line' => ['nullable', 'integer', 'min:0'],
            'browser' => ['nullable', 'string', 'max:120'],
        ]);

        $recorder->recordBrowser($data);

        return response()->json(status: 202);
    }
}
