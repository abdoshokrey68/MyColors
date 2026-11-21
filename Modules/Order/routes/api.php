<?php

use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\AdminOrderController;
use Modules\Order\Http\Controllers\CheckoutController;
use Modules\Order\Http\Controllers\OrderController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('checkout', [CheckoutController::class, 'store'])
        ->middleware('throttle:checkout')
        ->name('checkout.store');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{id}', [OrderController::class, 'show'])
        ->whereNumber('id')
        ->name('orders.show');
    Route::post('orders/{id}/cancel', [OrderController::class, 'cancel'])
        ->whereNumber('id')
        ->name('orders.cancel');
});

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin/orders')
    ->name('admin.orders.')
    ->group(function () {
        Route::patch('{id}/status', [AdminOrderController::class, 'updateStatus'])
            ->whereNumber('id')
            ->name('status');
    });
