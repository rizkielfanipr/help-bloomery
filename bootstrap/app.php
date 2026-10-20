<?php

use App\Http\Middleware\AssignMonitoringCorrelationId;
use App\Services\Monitoring\ErrorRecorder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AssignMonitoringCorrelationId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // System Monitoring only observes reported exceptions; default logging and rendering
        // continue unchanged (docs/system-monitoring-prd.md §13.1).
        $exceptions->report(function (Throwable $exception): void {
            app(ErrorRecorder::class)->record($exception);
        });
    })->create();
