<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentCancellationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->recipient->email,
            subject: 'Appuntamento cancellato: ' . $this->appointment->services_label,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-cancellation',
            with: ['noGreeting' => true],
        );
    }
}
