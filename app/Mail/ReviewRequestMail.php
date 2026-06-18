<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $appointmentsUrl = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->appointment->user->email,
            subject: 'Come è andata? Lascia una recensione',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.review-request',
            with: [
                'appointmentsUrl' => $this->appointmentsUrl ?: route('portal.appointments.index'),
                'noGreeting'      => true,
            ],
        );
    }
}
