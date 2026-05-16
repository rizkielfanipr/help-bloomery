<?php

use Illuminate\Support\Facades\Route;

// Redirect Breeze login ke panel helpdesk — auth ditangani oleh Filament per panel
Route::get('login', fn () => redirect('/helpdesk/login'))->name('login');
Route::get('register', fn () => redirect('/helpdesk/login'));
