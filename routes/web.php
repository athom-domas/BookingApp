<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Portal\AppointmentController as PortalAppointmentController;
use App\Http\Controllers\Portal\BookingController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BookingController::class, 'index'])->name('booking.index');
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/portal', [PortalAppointmentController::class, 'dashboard'])->name('portal.dashboard');
    Route::get('/portal/appointments', [PortalAppointmentController::class, 'index'])->name('portal.appointments.index');
    Route::get('/portal/appointments/{appointment}', [PortalAppointmentController::class, 'show'])->name('portal.appointments.show');
    Route::post('/portal/bookings', [BookingController::class, 'store'])->name('portal.bookings.store');
    Route::get('/portal/appointments/{appointment}/payment', [PortalAppointmentController::class, 'payment'])->name('portal.appointments.payment');
    Route::post('/portal/appointments/{appointment}/payment/confirm', [PortalAppointmentController::class, 'confirmPayment'])->name('portal.appointments.payment.confirm');
    Route::post('/portal/appointments/{appointment}/cancel', [PortalAppointmentController::class, 'cancel'])->name('portal.appointments.cancel');
});
