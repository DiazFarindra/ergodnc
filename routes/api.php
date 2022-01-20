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
Route::controller(OfficeController::class)->group(function () {
    Route::get('/offices', 'index');
    Route::get('/offices/{office}', 'show');

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::post('/offices', 'store');
        Route::put('/offices/{office}', 'update');
        Route::delete('/offices/{office}', 'destroy');
    });
});
