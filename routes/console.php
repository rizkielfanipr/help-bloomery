<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('service-requests:complete-warranty')->daily();
Schedule::command('briefing:auto-reject')->everyMinute()->withoutOverlapping();
Schedule::command('briefing:compute-scores')->monthlyOn(1, '02:00');
