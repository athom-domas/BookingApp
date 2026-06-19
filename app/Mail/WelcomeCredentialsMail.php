<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $recipient,
        public readonly string $tempPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->recipient->email,
            subject: 'Benvenuto! Le tue credenziali di accesso',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-credentials',
        );
    }
}
