<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class AppointmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->appointment->user->email,
            subject: 'Promemoria: ' . $this->appointment->services_label,
        );
    }

    public function content(): Content
    {
        $expiry = $this->appointment->scheduled_date->copy()->subDay();

        return new Content(
            view: 'emails.appointment-reminder',
            with: [
                'confirmUrl' => URL::signedRoute('appointment.public.confirm', ['appointment' => $this->appointment, 'uid' => $this->appointment->user_id], $expiry),
                'cancelUrl'  => URL::signedRoute('appointment.public.cancel', ['appointment' => $this->appointment, 'uid' => $this->appointment->user_id], $expiry),
            ],
        );
    }
}
