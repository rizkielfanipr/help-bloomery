<?php

use Illuminate\Support\Facades\Route;

// Root redirect ke panel helpdesk
Route::get('/', fn () => redirect('/helpdesk'));

require __DIR__.'/auth.php';
require __DIR__.'/driver.php';
