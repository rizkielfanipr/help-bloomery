<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:super_admin,hr_staff,casual_staff'])->group(function () {
    Route::get('/dashboard', fn () => view('casual.dashboard'))->name('casual.dashboard');
});
