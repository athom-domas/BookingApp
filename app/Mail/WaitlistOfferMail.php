<?php

namespace App\Mail;

use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistOfferMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly WaitlistEntry $entry,
        public readonly string $offerUrl,
    ) {}

    public function envelope(): Envelope
    {
        $expiresAt = $this->entry->offer_expires_at?->format('H:i') ?? '';

        return new Envelope(
            to:      $this->entry->user->email,
            subject: "Posto disponibile! Prenota entro le {$expiresAt}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.waitlist-offer');
    }
}
