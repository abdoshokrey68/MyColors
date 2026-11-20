<?php

use Illuminate\Support\Facades\Route;
use Modules\Cart\Http\Controllers\CartController;

Route::middleware('auth:sanctum')->prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'show'])->name('show');
    Route::post('items', [CartController::class, 'storeItem'])->name('items.store');
    Route::patch('items/{id}', [CartController::class, 'updateItem'])
        ->whereNumber('id')
        ->name('items.update');
    Route::delete('items/{id}', [CartController::class, 'destroyItem'])
        ->whereNumber('id')
        ->name('items.destroy');
});
