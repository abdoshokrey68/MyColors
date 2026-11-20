<?php

use Illuminate\Support\Facades\Route;
use Modules\Promotion\Http\Controllers\PromotionController;

Route::middleware('auth:sanctum')->prefix('promotions')->name('promotions.')->group(function () {
    Route::post('validate', [PromotionController::class, 'validateCode'])->name('validate');
});
