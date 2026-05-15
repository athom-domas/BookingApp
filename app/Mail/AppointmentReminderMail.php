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
            subject: 'Promemoria: ' . $this->appointment->service->name,
        );
    }

    public function content(): Content
    {
        $hoursLabel = $this->appointment->scheduled_date->isAfter(now()->addHours(20))
            ? 'domani'
            : 'tra 2 ore';

        return new Content(
            view: 'emails.appointment-reminder',
            with: [
                'confirmUrl' => URL::signedRoute('appointment.public.confirm', ['appointment' => $this->appointment], now()->addHours(48)),
                'cancelUrl'  => URL::signedRoute('appointment.public.cancel', ['appointment' => $this->appointment], now()->addHours(48)),
                'hoursLabel' => $hoursLabel,
            ],
        );
    }
}
