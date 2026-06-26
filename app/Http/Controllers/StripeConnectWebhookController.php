<?php

namespace App\Http\Controllers;

use App\Models\StripeConnectAccount;
use App\Models\StripeWebhookEvent;
use App\Services\StripeConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeConnectWebhookController extends Controller
{
    public function __construct(private readonly StripeConnectService $connectService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.stripe.connect_webhook_secret');

        if (empty($secret)) {
            if (app()->isProduction()) {
                return response()->json(['error' => 'Webhook secret not configured'], 400);
            }
            $payload   = $request->all();
            $eventId   = $payload['id'] ?? null;
            $type      = $payload['type'] ?? null;
            $accountId = $payload['account'] ?? null;
        } else {
            try {
                $event = Webhook::constructEvent(
                    $request->getContent(),
                    $request->header('Stripe-Signature', ''),
                    $secret,
                );
            } catch (UnexpectedValueException|SignatureVerificationException) {
                return response()->json(['message' => 'Invalid signature.'], 400);
            }
            $payload   = $event->toArray();
            $eventId   = $event->id;
            $type      = $event->type;
            $accountId = $event->account ?? null;
        }

        if (! $eventId) {
            return response()->json(['received' => true]);
        }

        try {
            StripeWebhookEvent::create([
                'event_id'   => $eventId,
                'account_id' => $accountId,
                'type'       => $type,
                'payload'    => $payload,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return response()->json(['received' => true]);
        }

        if ($type === 'account.updated' && $accountId) {
            $account = StripeConnectAccount::where('stripe_account_id', $accountId)->first();
            if ($account) {
                try {
                    $this->connectService->syncFromStripe($account);
                    StripeWebhookEvent::where('event_id', $eventId)
                        ->update(['processed_at' => now()]);
                } catch (\Throwable $e) {
                    StripeWebhookEvent::where('event_id', $eventId)
                        ->update(['failed_at' => now(), 'error_message' => $e->getMessage()]);
                }
            }
        } else {
            StripeWebhookEvent::where('event_id', $eventId)->update(['processed_at' => now()]);
        }

        return response()->json(['received' => true]);
    }
}
