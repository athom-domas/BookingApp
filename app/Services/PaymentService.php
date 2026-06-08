<?php

namespace App\Services;

use App\Events\AppointmentConfirmed;
use App\Events\PaymentCompleted;
use App\Events\PaymentRefunded;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use App\Services\LoyaltyService;

class PaymentService
{
    public function __construct(private readonly StripeClient $stripe) {}

    public function initiateStripePayment(int $appointmentId, int $amountCents): Payment
    {
        $appointment = Appointment::findOrFail($appointmentId);

        $paymentIntent = $this->stripe->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => 'eur',
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'appointment_id' => $appointmentId,
                'business_id'    => app()->bound('current_business_id') ? app('current_business_id') : null,
            ],
        ]);

        $payment = Payment::create([
            'appointment_id' => $appointmentId,
            'user_id' => $appointment->user_id,
            'amount' => $amountCents / 100,
            'status' => 'pending',
            'payment_method' => 'stripe',
            'stripe_transaction_id' => $paymentIntent->id,
            'stripe_response' => $paymentIntent->toArray(),
        ]);

        // Pre-accredito punti fedeltà subito, così sono già visibili sulla pagina di pagamento.
        // Se il pagamento fallisce/viene cancellato, i punti vengono stornati (vedere handleStripeWebhook).
        $this->preCreditLoyalty($appointment);

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
            $this->markPaymentCompleted($payment);
        } elseif ($type === 'payment_intent.payment_failed') {
            $payment->update(['status' => 'failed']);
            PaymentRefunded::dispatch($payment);
        } elseif ($type === 'payment_intent.canceled') {
            $payment->update(['status' => 'cancelled']);
            PaymentRefunded::dispatch($payment);
        }
    }

    public function confirmPayment(int $appointmentId): Payment
    {
        $appointment = Appointment::findOrFail($appointmentId);
        $payment = $appointment->payment;

        if (! $payment) {
            throw new BookingException('Nessun pagamento trovato per questo appuntamento.');
        }

        $paymentIntent = $this->stripe->paymentIntents->retrieve($payment->stripe_transaction_id);

        if ($paymentIntent->status === 'succeeded') {
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
                $this->stripe->paymentIntents->cancel($payment->stripe_transaction_id);
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

        if ($payment->status !== 'completed') {
            throw new BookingException('Solo i pagamenti completati possono essere rimborsati.');
        }

        $refund = $this->stripe->refunds->create([
            'payment_intent' => $payment->stripe_transaction_id,
        ]);

        $payment->update([
            'status' => 'refunded',
            'stripe_response' => $refund->toArray(),
        ]);

        PaymentRefunded::dispatch($payment);

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

    private function preCreditLoyalty(Appointment $appointment): void
    {
        $price = (float) ($appointment->final_price ?? 0);
        if ($price <= 0) {
            return;
        }

        if (! app()->bound('current_business_id')) {
            app()->instance('current_business_id', $appointment->business_id);
        }

        app(LoyaltyService::class)->accrue($appointment, $price);
    }
}
