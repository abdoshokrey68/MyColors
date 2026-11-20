<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Order Module API Routes
|--------------------------------------------------------------------------
|
| POST   /api/checkout
| GET    /api/orders
| GET    /api/orders/{id}
| POST   /api/orders/{id}/cancel
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // Routes will be registered in later phases.
});
