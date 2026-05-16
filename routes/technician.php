<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:super_admin,helpdesk_manager,technician'])->group(function () {
    Route::get('/dashboard', fn () => view('technician.dashboard'))->name('technician.dashboard');
});
