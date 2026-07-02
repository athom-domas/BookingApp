<?php

namespace App\Services;

use App\Events\AppointmentConfirmed;
use App\Events\PaymentCompleted;
use App\Events\PaymentRefunded;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class PaymentService
{
    public function __construct(
        private readonly ?StripeClient $stripe,
        private readonly ?StripeConnectService $connectService = null,
    ) {}

    public function initiateStripePayment(int $appointmentId, int $amountCents, ?Business $business = null): Payment
    {
        $appointment = Appointment::findOrFail($appointmentId);
        $business ??= Business::find(app()->bound('current_business_id') ? app('current_business_id') : null);

        $connectAccount = $business?->stripeConnectAccount;
        $hasConnect = $connectAccount && $connectAccount->isActive() && $this->connectService;

        $fee = $hasConnect
            ? $this->connectService->calculatePlatformFee($business, $amountCents)
            : ['cents' => 0, 'percent' => null];

        $pmConfig = config('services.stripe.payment_method_configuration');

        $intentParams = [
            'amount'   => $amountCents,
            'currency' => 'eur',
            'metadata' => [
                'appointment_id' => $appointmentId,
                'business_id'    => $business?->id,
            ],
        ];

        if ($hasConnect) {
            $intentParams['application_fee_amount']    = $fee['cents'];
            $intentParams['automatic_payment_methods'] = ['enabled' => true];
            $paymentIntent = $this->stripe->paymentIntents->create(
                $intentParams,
                ['stripe_account' => $connectAccount->stripe_account_id]
            );
        } elseif ($pmConfig) {
            $intentParams['payment_method_configuration'] = $pmConfig;
            $paymentIntent = $this->stripe->paymentIntents->create($intentParams);
        } else {
            $intentParams['automatic_payment_methods'] = ['enabled' => true];
            $paymentIntent = $this->stripe->paymentIntents->create($intentParams);
        }

        $payment = Payment::create([
            'appointment_id'        => $appointmentId,
            'user_id'               => $appointment->user_id,
            'amount'                => $amountCents / 100,
            'status'                => 'pending',
            'payment_method'        => 'stripe',
            'stripe_transaction_id' => $paymentIntent->id,
            'stripe_response'       => $paymentIntent->toArray(),
            'stripe_account_id'     => $hasConnect ? $connectAccount->stripe_account_id : null,
            'platform_fee_amount'   => round($fee['cents'] / 100, 2),
            'platform_fee_percent'  => $fee['percent'],
        ]);

        return $payment;
    }

    public function handleStripeWebhook(array $payload): void
    {
        $type = $payload['type'] ?? '';
        $transactionId = $payload['data']['object']['id'] ?? null;

        if (! $transactionId) {
            Log::warning('PaymentService: webhook payload missing transaction ID', ['type' => $payload['type'] ?? 'unknown']);

            return;
        }

        $payment = Payment::where('stripe_transaction_id', $transactionId)->first();

        if (! $payment) {
            return;
        }

        if ($type === 'payment_intent.succeeded') {
            $chargeId = $payload['data']['object']['latest_charge'] ?? null;
            $appFeeId = $payload['data']['object']['application_fee'] ?? null;
            if ($chargeId) {
                $updates = ['stripe_charge_id' => $chargeId];
                if ($appFeeId) {
                    $updates['stripe_application_fee_id'] = $appFeeId;
                }
                $payment->update($updates);
            }
            $this->markPaymentCompleted($payment);
        } elseif ($type === 'payment_intent.payment_failed') {
            $payment->update(['status' => 'failed']);
            PaymentRefunded::dispatch($payment);
        } elseif ($type === 'payment_intent.canceled') {
            $payment->update(['status' => 'cancelled']);
            PaymentRefunded::dispatch($payment);
        } elseif ($type === 'charge.refunded') {
            app(RefundService::class)->handleExternalRefund($payload['data']['object']);
        }
    }

    public function confirmPayment(int $appointmentId): Payment
    {
        $appointment = Appointment::findOrFail($appointmentId);
        $payment = $appointment->payment;

        if (! $payment) {
            throw new BookingException('Nessun pagamento trovato per questo appuntamento.');
        }

        $opts = $payment->stripe_account_id
            ? ['stripe_account' => $payment->stripe_account_id]
            : [];

        $paymentIntent = $this->stripe->paymentIntents->retrieve(
            $payment->stripe_transaction_id,
            [],
            $opts
        );

        if ($paymentIntent->status === 'succeeded') {
            $chargeId = $paymentIntent->latest_charge ?? null;
            if ($chargeId && ! $payment->stripe_charge_id) {
                $payment->update(['stripe_charge_id' => $chargeId]);
            }
            $this->markPaymentCompleted($payment);
        } elseif (in_array($paymentIntent->status, ['canceled', 'requires_payment_method'], true)) {
            throw new BookingException('Il pagamento non è andato a buon fine.');
        }

        return $payment->fresh();
    }

    public function cancelPendingPayment(Payment $payment): void
    {
        if ($payment->status !== 'pending') {
            return;
        }

        if ($payment->payment_method === 'stripe' && $payment->stripe_transaction_id) {
            try {
                $opts = $payment->stripe_account_id
                    ? ['stripe_account' => $payment->stripe_account_id]
                    : [];
                $this->stripe->paymentIntents->cancel($payment->stripe_transaction_id, [], $opts);
            } catch (\Throwable) {
                // PaymentIntent già cancellato o scaduto: nessuna azione necessaria
            }
        }

        $payment->update(['status' => 'cancelled']);
        PaymentRefunded::dispatch($payment);
    }

    public function refundPayment(int $paymentId): Payment
    {
        $payment = Payment::findOrFail($paymentId);
        app(\App\Services\RefundService::class)->refund($payment);
        return $payment->fresh();
    }

    public function recordInPersonPayment(int $appointmentId, string $method, float $amount): Payment
    {
        $appointment = Appointment::findOrFail($appointmentId);

        $existing = $appointment->payment;
        if ($existing && $existing->status === 'completed') {
            throw new BookingException('Esiste già un pagamento completato per questo appuntamento.');
        }

        $payment = Payment::create([
            'appointment_id' => $appointmentId,
            'user_id'        => $appointment->user_id,
            'amount'         => $amount,
            'status'         => 'pending',
            'payment_method' => $method,
        ]);

        $this->markPaymentCompleted($payment);

        return $payment->fresh();
    }

    public function applyLoyaltyDiscount(Payment $payment, int $percentage, float $originalAmount): void
    {
        $discounted = round($originalAmount * (1 - $percentage / 100), 2);

        $this->stripe->paymentIntents->update($payment->stripe_transaction_id, [
            'amount' => (int) round($discounted * 100),
        ]);

        $payment->update([
            'amount'                      => $discounted,
            'loyalty_discount_percentage' => $percentage,
            'loyalty_original_amount'     => $originalAmount,
        ]);
    }

    public function removeLoyaltyDiscount(Payment $payment): void
    {
        $original = (float) $payment->loyalty_original_amount;

        $this->stripe->paymentIntents->update($payment->stripe_transaction_id, [
            'amount' => (int) round($original * 100),
        ]);

        $payment->update([
            'amount'                      => $original,
            'loyalty_discount_percentage' => null,
            'loyalty_original_amount'     => null,
        ]);
    }

    private function markPaymentCompleted(Payment $payment): void
    {
        $alreadyCompleted = $payment->status === 'completed';

        $payment->update(['status' => 'completed']);

        $appointment = $payment->appointment;

        if (! $appointment) {
            return;
        }

        if (! in_array($appointment->status, ['confirmed', 'completed', 'cancelled'])) {
            $appointment->update(['status' => 'confirmed']);
        }

        if (! $alreadyCompleted) {
            PaymentCompleted::dispatch($payment);
        }

        if (! $alreadyCompleted && $payment->payment_method === 'stripe') {
            AppointmentConfirmed::dispatch($appointment->fresh());
        }
    }

}
