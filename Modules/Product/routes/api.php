<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\AdminProductController;
use Modules\Product\Http\Controllers\ProductController;

Route::prefix('products')->name('products.')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::get('{id}', [ProductController::class, 'show'])
        ->whereNumber('id')
        ->name('show');
});

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin/products')
    ->name('admin.products.')
    ->group(function () {
        Route::get('/', [AdminProductController::class, 'index'])->name('index');
        Route::post('/', [AdminProductController::class, 'store'])->name('store');
        Route::patch('{id}', [AdminProductController::class, 'update'])
            ->whereNumber('id')
            ->name('update');
        Route::delete('{id}', [AdminProductController::class, 'destroy'])
            ->whereNumber('id')
            ->name('destroy');
    });
