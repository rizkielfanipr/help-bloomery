<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeAppFeaturePermission
{
    /** @var array<string, string> */
    private const ROUTE_PERMISSIONS = [
        'attendance-page' => 'access employee app attendance',
        'clock-page' => 'access employee app attendance',
        'positions-page' => 'access employee app attendance',
        'briefing-' => 'access employee app briefing',
        'daily-briefing-page' => 'access employee app briefing',
        'trip-' => 'access employee app driver',
        'trip-dashboard' => 'access employee app driver',
        'active-trip' => 'access employee app driver',
        'start-trip' => 'access employee app driver',
        'fuel-fillup' => 'access employee app driver',
        'driver-profile-page' => 'access employee app driver',
        'technician-request-' => 'access employee app technician request',
        'service-requests' => 'access employee app technician',
        'technician-history-page' => 'access employee app technician',
        'technician-profile-page' => 'access employee app technician',
        'sales-report-' => 'access employee app sales report',
        '.report-page' => 'access employee app attendance',
        'stock-card-' => 'access employee app stock card',
        'purchase-request-' => 'access employee app purchasing',
        'design-request-' => 'access employee app design',
        'erp-request-' => 'access employee app erp',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) $request->route()?->getName();

        foreach (self::ROUTE_PERMISSIONS as $routeFragment => $permission) {
            if (str_contains($routeName, $routeFragment)) {
                abort_unless($request->user()?->can($permission), 403);

                break;
            }
        }

        return $next($request);
    }
}
