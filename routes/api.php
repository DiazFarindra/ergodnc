<?php

use App\Http\Controllers\{
    HostReservationController,
    OfficeController,
    OfficeImageController,
    TagController,
    UserReservationController
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

// tags...
Route::get('/tags', TagController::class);

// offices...
Route::get('/offices', [OfficeController::class, 'index']);
Route::get('/offices/{office}', [OfficeController::class, 'show']);
Route::post('/offices', [OfficeController::class, 'store'])->middleware(['auth:sanctum', 'verified']);
Route::put('/offices/{office}', [OfficeController::class, 'update'])->middleware(['auth:sanctum', 'verified']);
Route::delete('/offices/{office}', [OfficeController::class, 'destroy'])->middleware(['auth:sanctum', 'verified']);

// offices photos...
Route::put('/offices/{office}/images', [OfficeImageController::class, 'store'])->middleware(['auth:sanctum', 'verified']);
Route::delete('/offices/{office}/images/{image:id}', [OfficeImageController::class, 'destroy'])->middleware(['auth:sanctum', 'verified']);


// User Reservations...
Route::get('/reservations', [UserReservationController::class, 'index'])->middleware(['auth:sanctum', 'verified']);
Route::post('/reservations', [UserReservationController::class, 'store'])->middleware(['auth:sanctum', 'verified']);

// Host Reservations...
Route::get('/host/reservations', [HostReservationController::class, 'index'])->middleware(['auth:sanctum', 'verified']);
