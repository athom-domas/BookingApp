<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class PaymentService
{
    public function __construct(private readonly StripeClient $stripe) {}

    public function initiateStripePayment(int $appointmentId, int $amountCents): Payment
    {
        $appointment = Appointment::findOrFail($appointmentId);

        $paymentIntent = $this->stripe->paymentIntents->create([
            'amount'   => $amountCents,
            'currency' => 'eur',
            'metadata' => ['appointment_id' => $appointmentId],
        ]);

        return Payment::create([
            'appointment_id'        => $appointmentId,
            'user_id'               => $appointment->user_id,
            'amount'                => $amountCents / 100,
            'status'                => 'pending',
            'stripe_transaction_id' => $paymentIntent->id,
            'stripe_response'       => $paymentIntent->toArray(),
        ]);
    }

    public function handleStripeWebhook(array $payload): void
    {
        $type          = $payload['type'] ?? '';
        $transactionId = $payload['data']['object']['id'] ?? null;

        if (! $transactionId) {
            Log::warning('PaymentService: webhook payload missing transaction ID', ['type' => $payload['type'] ?? 'unknown']);
            return;
        }

        $payment = Payment::where('stripe_transaction_id', $transactionId)->first();

        if (! $payment) {
            return;
        }

        match ($type) {
            'payment_intent.succeeded'      => $payment->update(['status' => 'completed']),
            'payment_intent.payment_failed' => $payment->update(['status' => 'failed']),
            'payment_intent.canceled'       => $payment->update(['status' => 'cancelled']),
            default                         => null,
        };
    }

    public function confirmPayment(int $appointmentId): Payment
    {
        $appointment = Appointment::findOrFail($appointmentId);
        $payment     = $appointment->payment;

        if (! $payment) {
            throw new BookingException('Nessun pagamento trovato per questo appuntamento.');
        }

        $paymentIntent = $this->stripe->paymentIntents->retrieve($payment->stripe_transaction_id);

        match ($paymentIntent->status) {
            'succeeded' => $payment->update(['status' => 'completed']),
            'canceled', 'requires_payment_method' => throw new BookingException('Il pagamento non è andato a buon fine.'),
            default     => null,
        };

        return $payment->fresh();
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
            'status'          => 'refunded',
            'stripe_response' => $refund->toArray(),
        ]);

        return $payment->fresh();
    }
}
