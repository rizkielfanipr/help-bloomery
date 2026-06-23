<?php

use App\Http\Controllers\Helpdesk\CasualClockRecordExportController;
use Illuminate\Support\Facades\Route;

// Root redirect ke panel helpdesk
Route::get('/', fn () => redirect('/helpdesk'));

Route::middleware(['auth'])->group(function (): void {
    Route::get('/helpdesk/exports/casual-clock-records', CasualClockRecordExportController::class)
        ->name('helpdesk.exports.casual-clock-records');
});

// Standalone UI mockups
Route::get('/ui/helpdesk', fn () => view('ui.helpdesk-dashboard'))->name('ui.helpdesk-dashboard');

require __DIR__.'/auth.php';
require __DIR__.'/driver.php';
