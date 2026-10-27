<?php

namespace App\Providers;

use App\Services\Monitoring\EsbTransactionRecorder;
use App\Services\Monitoring\ScheduledTaskRecorder;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires System Monitoring (docs/products/system-monitoring/prd.md §22) into the existing application
 * without changing any business flow.
 */
class MonitoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: one recorder per request / queued job so open scopes never leak between them.
        $this->app->scoped(EsbTransactionRecorder::class);
        $this->app->singleton(ScheduledTaskRecorder::class);
    }

    public function boot(): void
    {
        Event::listen(RequestSending::class, fn (RequestSending $event) => $this->app->make(EsbTransactionRecorder::class)->onRequestSending($event));
        Event::listen(ResponseReceived::class, fn (ResponseReceived $event) => $this->app->make(EsbTransactionRecorder::class)->onResponseReceived($event));
        Event::listen(ConnectionFailed::class, fn (ConnectionFailed $event) => $this->app->make(EsbTransactionRecorder::class)->onConnectionFailed($event));

        Event::listen(ScheduledTaskStarting::class, fn (ScheduledTaskStarting $event) => $this->app->make(ScheduledTaskRecorder::class)->starting($event));
        Event::listen(ScheduledTaskFinished::class, fn (ScheduledTaskFinished $event) => $this->app->make(ScheduledTaskRecorder::class)->finished($event));
        Event::listen(ScheduledTaskFailed::class, fn (ScheduledTaskFailed $event) => $this->app->make(ScheduledTaskRecorder::class)->failed($event));
        Event::listen(ScheduledTaskSkipped::class, fn (ScheduledTaskSkipped $event) => $this->app->make(ScheduledTaskRecorder::class)->skipped($event));
    }
}
