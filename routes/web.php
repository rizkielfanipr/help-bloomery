<?php

use Illuminate\Support\Facades\Route;

// Root redirect ke panel helpdesk
Route::get('/', fn () => redirect('/helpdesk'));

// Standalone UI mockups
Route::get('/ui/helpdesk', fn () => view('ui.helpdesk-dashboard'))->name('ui.helpdesk-dashboard');

require __DIR__.'/auth.php';
require __DIR__.'/driver.php';
