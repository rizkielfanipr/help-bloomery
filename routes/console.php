<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('service-requests:complete-warranty')->daily();
Schedule::command('erp-requests:complete-warranty')->hourly()->withoutOverlapping();
Schedule::command('briefing:auto-reject')->everyMinute()->withoutOverlapping();
Schedule::command('briefing:compute-scores')->monthlyOn(1, '02:00');
Schedule::command('rnd:sync-esb-materials')->dailyAt('02:30')->withoutOverlapping(30);
Schedule::command('product-price-index:sync --days=90')
    ->weeklyOn(1, '01:00')
    ->withoutOverlapping(180);

Schedule::command('sales-reports:auto-reject')->everyMinute()->withoutOverlapping();
Schedule::command('basket-size:finalize')->everyTenMinutes()->withoutOverlapping(30);
Schedule::command('rnd:send-project-task-reminders')->dailyAt('07:00')->withoutOverlapping();

// System Monitoring (docs/system-monitoring-prd.md §10, §19): read-only checks every minute and
// local retention/aggregation at night. No task sends ESB mutations.
Schedule::command('monitoring:run-checks')->everyMinute()->withoutOverlapping(5);
Schedule::command('monitoring:aggregate')->dailyAt('00:20')->withoutOverlapping(30);
Schedule::command('monitoring:prune')->dailyAt('03:15')->withoutOverlapping(60);
