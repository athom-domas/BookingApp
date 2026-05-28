<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = \App\Models\IntegrationSetting::getStripeWebhookSecret() ?? config('services.stripe.webhook_secret');

        if (! $secret) {
            return response()->json(['message' => 'Stripe webhook secret is not configured.'], 500);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $secret,
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response()->json(['message' => 'Invalid Stripe webhook signature.'], 400);
        }

        $businessId = $event->data->object?->metadata?->business_id ?? null;
        if ($businessId) {
            app()->instance('current_business_id', (int) $businessId);
        }

        $this->paymentService->handleStripeWebhook($event->toArray());

        return response()->json(['received' => true]);
    }
}
