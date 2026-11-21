<?php

use Illuminate\Support\Facades\Route;
use Modules\Promotion\Http\Controllers\AdminPromotionController;
use Modules\Promotion\Http\Controllers\PromotionController;

Route::middleware('auth:sanctum')->prefix('promotions')->name('promotions.')->group(function () {
    Route::post('validate', [PromotionController::class, 'validateCode'])->name('validate');
});

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin/promotions')
    ->name('admin.promotions.')
    ->group(function () {
        Route::get('/', [AdminPromotionController::class, 'index'])->name('index');
        Route::post('/', [AdminPromotionController::class, 'store'])->name('store');
        Route::patch('{id}', [AdminPromotionController::class, 'update'])
            ->whereNumber('id')
            ->name('update');
        Route::delete('{id}', [AdminPromotionController::class, 'destroy'])
            ->whereNumber('id')
            ->name('destroy');
    });
