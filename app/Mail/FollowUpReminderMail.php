<?php

namespace App\Mail;

use App\Models\FollowUpReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class FollowUpReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly FollowUpReminder $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->reminder->user->email,
            subject: 'Vuoi prenotare un nuovo appuntamento?',
        );
    }

    public function content(): Content
    {
        $unsubscribeUrl = URL::signedRoute(
            'follow-up-reminders.unsubscribe',
            ['user' => $this->reminder->user_id],
            now()->addYear()
        );

        return new Content(
            view: 'emails.follow-up-reminder',
            with: [
                'reminder'       => $this->reminder,
                'bookingUrl'     => route('booking.index'),
                'unsubscribeUrl' => $unsubscribeUrl,
                'noGreeting'     => true,
            ],
        );
    }
}
