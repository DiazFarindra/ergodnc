<?php

use App\Http\Controllers\{
    OfficeController,
    TagController
};

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// tags
Route::get('/tags', TagController::class);

// offices
Route::prefix('/offices')->controller(OfficeController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('/{office}', 'show');

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::post('/', 'store');
        Route::put('/{office}', 'update');
        Route::delete('/{office}', 'destroy');
    });
});
