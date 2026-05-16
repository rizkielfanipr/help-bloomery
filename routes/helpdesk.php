<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:super_admin,helpdesk_manager,helpdesk_staff'])->group(function () {
    Route::get('/dashboard', fn () => view('helpdesk.dashboard'))->name('helpdesk.dashboard');
});
