<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Promotion Module API Routes
|--------------------------------------------------------------------------
|
| Coupon validation is applied during checkout.
| Admin coupon management can live here as a bonus.
|
*/

Route::prefix('promotions')->name('promotions.')->group(function () {
    // Routes will be registered in later phases.
});
