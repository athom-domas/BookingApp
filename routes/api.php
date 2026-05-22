<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

// ─── Dynamic booking (new slot system) ───────────────────────────────────────
Route::prefix('booking')->group(function () {
    Route::get('/slots', [BookingController::class, 'getAvailableSlots']);
    Route::get('/available-dates', [BookingController::class, 'getAvailableDates']);
});

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service}/slots', [ServiceController::class, 'slots']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
    Route::post('/appointments/{appointment}/payment', [PaymentController::class, 'confirm']);
});
