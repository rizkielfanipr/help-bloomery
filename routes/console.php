<?php

use App\Models\EsbProductionOrderSyncRun;
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

// System Monitoring (docs/products/system-monitoring/prd.md §10, §19): read-only checks every minute and
// local retention/aggregation at night. No task sends ESB mutations.
Schedule::command('monitoring:run-checks')->everyMinute()->withoutOverlapping(5);
Schedule::command('monitoring:aggregate')->dailyAt('00:20')->withoutOverlapping(30);
Schedule::command('monitoring:prune')->dailyAt('03:15')->withoutOverlapping(60);
// Ordering Recommendation: dispatch the previous day's snapshot after the data is final
// (docs/products/analytics/ordering-recommendation/prd.md §12.2). The time comes from config.
Schedule::command('ordering:sync')->dailyAt((string) config('ordering_recommendation.sync_time'))->withoutOverlapping(60)->onOneServer();
// Kitchen Production Order ESB (docs/products/kitchen/production-schedule/prd.md §30.6, K3, K12): hourly
// incremental window only, gated by a toggle that stays off until the rollout; never a backfill.
Schedule::command('kitchen:sync-production-orders')
    ->hourly()
    ->withoutOverlapping(30)
    ->onOneServer()
    ->when(fn (): bool => (bool) config('kitchen.production_orders.sync_enabled'));
Schedule::command('model:prune', ['--model' => [EsbProductionOrderSyncRun::class]])->dailyAt('03:40')->withoutOverlapping(30);
