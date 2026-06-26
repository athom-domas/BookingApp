<?php

namespace App\Services;

use App\Events\PaymentRefunded;
use App\Exceptions\BookingException;
use App\Models\Payment;
use App\Models\StripeRefund;
use Stripe\StripeClient;

class RefundService
{
    public function __construct(private readonly ?StripeClient $stripe) {}

    public function refund(Payment $payment, ?int $amountCents = null): StripeRefund
    {
        if (! $this->stripe) {
            throw new BookingException('Stripe non configurato. Verifica la chiave STRIPE_SECRET_KEY.');
        }

        if ($payment->status !== 'completed') {
            throw new BookingException('Solo i pagamenti completati possono essere rimborsati.');
        }

        if (empty($payment->stripe_charge_id)) {
            throw new \App\Exceptions\BookingException('Impossibile rimborsare: charge ID non ancora disponibile. Riprovare tra qualche istante.');
        }

        $params = ['charge' => $payment->stripe_charge_id];

        if ($payment->stripe_account_id !== null) {
            $params['reverse_transfer']      = true;
            $params['refund_application_fee'] = true;
        }

        if ($amountCents !== null) {
            $params['amount'] = $amountCents;
        }

        $stripeRefund = $this->stripe->refunds->create($params);

        $isConnect = $payment->stripe_account_id !== null;

        $refundRecord = StripeRefund::create([
            'payment_id'             => $payment->id,
            'stripe_refund_id'       => $stripeRefund->id,
            'amount'                 => $stripeRefund->amount,
            'status'                 => $stripeRefund->status,
            'reason'                 => $stripeRefund->reason ?? null,
            'refund_application_fee' => $isConnect,
            'reverse_transfer'       => $isConnect,
            'payload'                => $stripeRefund->toArray(),
        ]);

        if ($stripeRefund->status === 'succeeded' && $amountCents === null) {
            $payment->update(['status' => 'refunded']);
            PaymentRefunded::dispatch($payment);
        }

        return $refundRecord;
    }

    public function handleExternalRefund(array $chargePayload): void
    {
        $chargeId = $chargePayload['id'] ?? null;
        if (! $chargeId) {
            return;
        }

        $payment = Payment::where('stripe_charge_id', $chargeId)->first();
        if (! $payment) {
            return;
        }

        $refunds = $chargePayload['refunds']['data'] ?? [];
        foreach ($refunds as $refundData) {
            $refundId = $refundData['id'] ?? null;
            if (! $refundId || StripeRefund::where('stripe_refund_id', $refundId)->exists()) {
                continue;
            }

            StripeRefund::create([
                'payment_id'             => $payment->id,
                'stripe_refund_id'       => $refundId,
                'amount'                 => $refundData['amount'],
                'status'                 => $refundData['status'] ?? 'succeeded',
                'reason'                 => $refundData['reason'] ?? null,
                'refund_application_fee' => false,
                'reverse_transfer'       => false,
                'payload'                => $refundData,
            ]);

            $totalRefunded = StripeRefund::where('payment_id', $payment->id)->sum('amount');

            if ($totalRefunded >= (int) round((float) $payment->amount * 100)) {
                $payment->update(['status' => 'refunded']);
                PaymentRefunded::dispatch($payment);
                break;
            }
        }
    }
}
