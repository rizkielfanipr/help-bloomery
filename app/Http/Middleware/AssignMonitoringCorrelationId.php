<?php

namespace App\Http\Middleware;

use App\Services\Monitoring\MonitoringContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every web request one correlation ID shared by monitoring transactions, error records,
 * log context, and jobs dispatched by the request. The response is not modified.
 */
class AssignMonitoringCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        MonitoringContext::correlationId();

        return $next($request);
    }
}
