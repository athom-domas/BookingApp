<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->recipient->email,
            subject: 'Scorte basse: ' . $this->product->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.low-stock-notification',
            with: ['adminUrl' => $this->buildUrl('/admin/products/' . $this->product->id . '/edit')],
        );
    }

    private function buildUrl(string $path): string
    {
        $business   = Business::withoutGlobalScopes()->find($this->product->business_id);
        $baseDomain = config('app.base_domain');
        $scheme     = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return ($business && $baseDomain)
            ? $scheme . '://' . $business->subdomain . '.' . $baseDomain . $path
            : url($path);
    }
}
