<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $data) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'demo'    => 'Richiesta demo',
            'info'    => 'Informazioni generali',
            'support' => 'Supporto tecnico',
            'other'   => 'Altra richiesta',
        ];

        return new Envelope(
            subject: '[GestionalePro] ' . ($subjects[$this->data['subject']] ?? 'Contatto dal sito'),
            replyTo: [$this->data['email']],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact');
    }
}
