<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Domain routes live inside each module routes/api.php file and are loaded
| by the module service provider. Keep this file for app-level endpoints.
|
*/

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'MyColors API is running.',
        'version' => 'v1',
    ]);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
