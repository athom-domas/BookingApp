<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Portal\AppointmentController as PortalAppointmentController;
use App\Http\Controllers\Portal\BookingController;
use App\Http\Controllers\Portal\SettingsController;
use App\Http\Controllers\Public\AppointmentActionController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BookingController::class, 'index'])->name('booking.index');
Route::get('/prenota', [BookingController::class, 'create'])->name('booking.create');
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::get('/r/waitlist/{entry}/accetta', [\App\Http\Controllers\WaitlistOfferController::class, 'accept'])
    ->name('waitlist.offer.accept')
    ->middleware('signed');

Route::get('/r/{appointment}/conferma', [AppointmentActionController::class, 'confirm'])
    ->name('appointment.public.confirm')
    ->middleware('signed');

Route::get('/r/{appointment}/disdici', [AppointmentActionController::class, 'cancelForm'])
    ->name('appointment.public.cancel')
    ->middleware('signed');

Route::post('/r/{appointment}/disdici', [AppointmentActionController::class, 'processCancellation'])
    ->name('appointment.public.cancel.post')
    ->middleware('signed');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::get('/password/forgot', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/password/forgot', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/password/reset/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/password/reset', [NewPasswordController::class, 'store'])->name('password.update');
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
    Route::get('/portal/settings', [SettingsController::class, 'index'])->name('portal.settings.index');
    Route::patch('/portal/settings/profile', [SettingsController::class, 'updateProfile'])->name('portal.settings.profile');
    Route::patch('/portal/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('portal.settings.notifications');
});
