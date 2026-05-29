<?php

namespace App\Http\Controllers;

use App\Mail\PaymentFailedMail;
use App\Models\Business;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Symfony\Component\HttpFoundation\Response;

class StripeBillingWebhookController extends WebhookController
{
    protected function webhookSecret(): string|null
    {
        return config('cashier.billing_webhook.secret');
    }

    public function handleInvoicePaymentFailed(array $payload): Response
    {
        $customerId = $payload['data']['object']['customer'] ?? null;

        if ($customerId) {
            $business = Business::where('stripe_id', $customerId)->first();

            if ($business) {
                $admin = $business->users()
                    ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                    ->first();

                if ($admin) {
                    Mail::to($admin->email)->send(new PaymentFailedMail($business));
                }
            }
        }

        return $this->successMethod();
    }
}
