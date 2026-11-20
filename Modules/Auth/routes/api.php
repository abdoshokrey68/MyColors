<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Module API Routes
|--------------------------------------------------------------------------
|
| POST /api/auth/register
| POST /api/auth/login
| POST /api/auth/logout
|
*/

Route::prefix('auth')->name('auth.')->group(function () {
    // Routes will be registered in Phase 2.
});
