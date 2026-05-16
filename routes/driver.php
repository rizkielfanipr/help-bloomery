<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:super_admin,helpdesk_manager,driver'])->group(function () {
    Route::get('/dashboard', fn () => view('driver.dashboard'))->name('driver.dashboard');
});
