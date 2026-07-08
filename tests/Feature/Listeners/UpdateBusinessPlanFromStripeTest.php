<?php

use App\Listeners\UpdateBusinessPlanFromStripe;
use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Events\WebhookHandled;

function makeWebhookHandledEvent(string $type, string $stripeCustomerId, ?string $stripePrice = null): WebhookHandled
{
    $object = ['customer' => $stripeCustomerId];
    if ($stripePrice) {
        $object['items'] = ['data' => [['price' => ['id' => $stripePrice]]]];
    }

    return new WebhookHandled([
        'type' => $type,
        'data' => ['object' => $object],
    ]);
}

it('updates plan to plus when subscription updated to plus price', function () {
    $plusPriceId = 'price_plus_test';
    config(['plans.plus.price_id' => $plusPriceId]);

    $business = Business::factory()->create(['stripe_id' => 'cus_test_002', 'plan' => 'base']);

    $subId = DB::table('subscriptions')->insertGetId([
        'business_id'   => $business->id,
        'type'          => 'default',
        'stripe_id'     => 'sub_test_001',
        'stripe_status' => 'active',
        'stripe_price'  => null,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    DB::table('subscription_items')->insert([
        'subscription_id' => $subId,
        'stripe_id'       => 'si_test_001',
        'stripe_product'  => 'prod_test',
        'stripe_price'    => $plusPriceId,
        'quantity'        => 1,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $listener = new UpdateBusinessPlanFromStripe();
    $listener->handle(makeWebhookHandledEvent('customer.subscription.updated', 'cus_test_002', $plusPriceId));

    expect($business->fresh()->plan)->toBe('plus');
});

it('resets plan to base when subscription is deleted', function () {
    $business = Business::factory()->create(['stripe_id' => 'cus_delete_001', 'plan' => 'plus']);

    $listener = new UpdateBusinessPlanFromStripe();
    $listener->handle(makeWebhookHandledEvent('customer.subscription.deleted', 'cus_delete_001'));

    expect($business->fresh()->plan)->toBe('base');
});

it('does nothing for unknown Stripe customer', function () {
    $listener = new UpdateBusinessPlanFromStripe();

    expect(fn () => $listener->handle(
        makeWebhookHandledEvent('customer.subscription.deleted', 'cus_unknown_999')
    ))->not->toThrow(\Throwable::class);
});

it('ignores unrelated webhook events', function () {
    $business = Business::factory()->create(['stripe_id' => 'cus_irrelevant', 'plan' => 'plus']);

    $listener = new UpdateBusinessPlanFromStripe();
    $listener->handle(makeWebhookHandledEvent('payment_intent.succeeded', 'cus_irrelevant'));

    expect($business->fresh()->plan)->toBe('plus');
});
