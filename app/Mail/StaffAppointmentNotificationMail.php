<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffAppointmentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->appointment->staff->email,
            subject: 'Nuovo appuntamento: ' . $this->appointment->services_label,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staff-appointment-notification',
        );
    }
}
