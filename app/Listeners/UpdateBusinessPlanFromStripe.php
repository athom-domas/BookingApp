<?php

namespace App\Listeners;

use App\Models\Business;
use Illuminate\Events\Attributes\ListensTo;
use Laravel\Cashier\Events\WebhookHandled;

#[ListensTo(WebhookHandled::class)]
class UpdateBusinessPlanFromStripe
{
    public function handle(WebhookHandled $event): void
    {
        $payload = $event->payload;
        $type    = $payload['type'] ?? null;

        if ($type === 'customer.subscription.updated') {
            $stripeCustomerId = $payload['data']['object']['customer'] ?? null;
            $business = Business::where('stripe_id', $stripeCustomerId)->first();

            if (! $business) {
                return;
            }

            $plusPriceId = config('plans.plus.price_id');
            $plan = $plusPriceId && $business->subscribedToPrice($plusPriceId, 'default')
                ? 'plus'
                : 'base';

            $business->update(['plan' => $plan]);
        }

        if ($type === 'customer.subscription.deleted') {
            $stripeCustomerId = $payload['data']['object']['customer'] ?? null;
            $business = Business::where('stripe_id', $stripeCustomerId)->first();

            if (! $business) {
                return;
            }

            $business->update(['plan' => 'base']);
        }
    }
}
