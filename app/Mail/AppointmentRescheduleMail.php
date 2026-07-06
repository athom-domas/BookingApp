<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentRescheduleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->appointment->user->email,
            subject: 'Appuntamento spostato: ' . $this->appointment->services_label,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-reschedule',
        );
    }
}
