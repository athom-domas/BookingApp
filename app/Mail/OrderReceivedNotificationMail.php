<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderReceivedNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ProductOrder $order,
        public readonly User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->recipient->email,
            subject: 'Nuovo ordine prodotti #' . $this->order->id . ' da ' . $this->order->user->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-received-notification',
            with: ['adminUrl' => $this->buildUrl('/admin/product-orders/' . $this->order->id), 'noGreeting' => true],
        );
    }

    private function buildUrl(string $path): string
    {
        $business   = Business::withoutGlobalScopes()->find($this->order->business_id);
        $baseDomain = config('app.base_domain');
        $scheme     = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return ($business && $baseDomain)
            ? $scheme . '://' . $business->subdomain . '.' . $baseDomain . $path
            : url($path);
    }
}
