<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Business $business) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[GestionalePro] Pagamento non riuscito — azione richiesta');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-failed', with: ['noGreeting' => true]);
    }
}
