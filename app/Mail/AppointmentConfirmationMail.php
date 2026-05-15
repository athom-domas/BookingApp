<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->appointment->user->email,
            subject: 'Appuntamento confermato: ' . $this->appointment->service->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-confirmation',
        );
    }
}
