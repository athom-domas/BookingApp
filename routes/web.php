<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Portal\AppointmentController as PortalAppointmentController;
use App\Http\Controllers\Portal\BookingController;
use App\Http\Controllers\Portal\ProductController;
use App\Http\Controllers\Portal\ProductOrderController;
use App\Http\Controllers\Portal\SettingsController;
use App\Http\Controllers\Portal\ReviewController;
use App\Http\Controllers\Portal\WaitlistController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Public\AppointmentActionController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('storefront.access')->group(function () {
    Route::get('/', function () {
        if (! app()->bound('current_business_id')) {
            return view('landing');
        }
        return app(BookingController::class)->index();
    })->name('booking.index');
    Route::get('/prenota', [BookingController::class, 'create'])->name('booking.create');
    Route::get('/portal/waitlist/create', [WaitlistController::class, 'create'])->name('portal.waitlist.create');

    Route::get('/prodotti', [ProductController::class, 'index'])->name('shop.index');
    Route::post('/prodotti/carrello', [ProductController::class, 'cartUpdate'])->name('shop.cart.update');
    Route::delete('/prodotti/carrello/{productId}', [ProductController::class, 'cartRemove'])->name('shop.cart.remove');
    Route::redirect('/portal/products', '/prodotti');
});
Route::get('/privacy', fn () => view('privacy'))->name('legal.privacy');
Route::get('/termini', fn () => view('terms'))->name('legal.terms');
Route::get('/contatti', [ContactController::class, 'create'])->name('contact');
Route::post('/contatti', [ContactController::class, 'store'])->name('contact.store')->middleware('throttle:5,1');
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
Route::post('/stripe/billing-webhook', [\App\Http\Controllers\StripeBillingWebhookController::class, 'handleWebhook'])
    ->name('stripe.billing.webhook');
Route::post('/stripe/connect/webhook', \App\Http\Controllers\StripeConnectWebhookController::class)
    ->name('stripe.connect.webhook');

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

Route::get('/follow-up-reminders/unsubscribe/{user}', \App\Http\Controllers\FollowUpReminderUnsubscribeController::class)
    ->name('follow-up-reminders.unsubscribe')
    ->middleware('signed');

Route::get('/auth/google', [SocialAuthController::class, 'redirect'])->name('auth.google')->middleware('guest');
Route::get('/auth/google/callback', [SocialAuthController::class, 'callback'])->name('auth.google.callback');
Route::get('/auth/google/exchange', [SocialAuthController::class, 'exchange'])->name('auth.google.exchange');

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

Route::middleware(['auth', 'tenant.user', 'tenant.status'])->group(function () {
    Route::get('/stripe/connect/start', [\App\Http\Controllers\StripeConnectController::class, 'start'])
        ->name('stripe.connect.start');
    Route::get('/stripe/connect/callback', [\App\Http\Controllers\StripeConnectController::class, 'callback'])
        ->name('stripe.connect.callback');
    Route::get('/stripe/connect/refresh', [\App\Http\Controllers\StripeConnectController::class, 'refresh'])
        ->name('stripe.connect.refresh');
    Route::get('/stripe/connect/dashboard', [\App\Http\Controllers\StripeConnectController::class, 'dashboardLink'])
        ->name('stripe.connect.dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/portal', [PortalAppointmentController::class, 'dashboard'])->name('portal.dashboard');
    Route::get('/portal/appointments', [PortalAppointmentController::class, 'index'])->name('portal.appointments.index');
    Route::get('/portal/appointments/{appointment}', [PortalAppointmentController::class, 'show'])->name('portal.appointments.show');
    Route::post('/portal/bookings', [BookingController::class, 'store'])->name('portal.bookings.store');
    Route::get('/portal/appointments/{appointment}/payment', [PortalAppointmentController::class, 'payment'])->name('portal.appointments.payment');
    Route::post('/portal/appointments/{appointment}/payment/confirm', [PortalAppointmentController::class, 'confirmPayment'])->name('portal.appointments.payment.confirm');
    Route::post('/portal/appointments/{appointment}/payment/discount', [PortalAppointmentController::class, 'applyDiscount'])->name('portal.appointments.payment.discount');
    Route::delete('/portal/appointments/{appointment}/payment/discount', [PortalAppointmentController::class, 'removeDiscount'])->name('portal.appointments.payment.discount.remove');
    Route::post('/portal/appointments/{appointment}/cancel', [PortalAppointmentController::class, 'cancel'])->name('portal.appointments.cancel');
    Route::get('/portal/appointments/{appointment}/review', [ReviewController::class, 'create'])->name('portal.appointments.review');
    Route::post('/portal/reviews', [ReviewController::class, 'store'])->name('portal.reviews.store');
    Route::get('/portal/settings', [SettingsController::class, 'index'])->name('portal.settings.index');
    Route::patch('/portal/settings/profile', [SettingsController::class, 'updateProfile'])->name('portal.settings.profile');
    Route::patch('/portal/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('portal.settings.notifications');
    Route::patch('/portal/settings/communications', [SettingsController::class, 'updateCommunications'])->name('portal.settings.communications');
    Route::patch('/portal/settings/booking-preferences', [SettingsController::class, 'updateBookingPreferences'])->name('portal.settings.booking-preferences');
    Route::patch('/portal/settings/preferences', [SettingsController::class, 'updatePreferences'])->name('portal.settings.preferences');
    Route::post('/portal/settings/booking-preferences/dismiss', [SettingsController::class, 'dismissBookingPreferencePrompt'])->name('portal.settings.booking-preferences.dismiss');

    Route::post('/portal/waitlist', [WaitlistController::class, 'store'])->name('portal.waitlist.store');
    Route::delete('/portal/waitlist/{entry}', [WaitlistController::class, 'destroy'])->name('portal.waitlist.destroy');

    Route::get('/prodotti/checkout', [ProductController::class, 'checkout'])->name('shop.checkout');
    Route::post('/prodotti/ordine', [ProductController::class, 'placeOrder'])->name('shop.order');
    Route::get('/prodotti/ordine/{orderId}/pagamento', [ProductController::class, 'payment'])->name('shop.payment');
    Route::get('/prodotti/ordine/{orderId}/stripe-confirm', [ProductController::class, 'confirmStripePayment'])->name('shop.stripe-confirm');
    Route::get('/prodotti/ordine/{orderId}/conferma', [ProductController::class, 'confirmation'])->name('shop.confirmation');

    Route::get('/portal/orders', [ProductOrderController::class, 'index'])->name('portal.orders.index');
});
