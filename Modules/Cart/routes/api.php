<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cart Module API Routes
|--------------------------------------------------------------------------
|
| GET    /api/cart
| POST   /api/cart/items
| PATCH  /api/cart/items/{id}
| DELETE /api/cart/items/{id}
|
*/

Route::middleware('auth:sanctum')->prefix('cart')->name('cart.')->group(function () {
    // Routes will be registered in Phase 2.
});
