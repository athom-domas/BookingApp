<?php

namespace App\Jobs;

use App\Mail\ReviewRequestMail;
use App\Models\Appointment;
use App\Models\SalonReview;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendReviewRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function handle(): void
    {
        app()->instance('current_business_id', $this->appointment->business_id);

        if (! SystemSetting::isReviewRequestEnabled()) {
            return;
        }

        if (SalonReview::where('appointment_id', $this->appointment->id)->exists()) {
            return;
        }

        $customer = $this->appointment->user;
        if (! $customer?->email) {
            return;
        }

        $baseDomain = config('app.base_domain');
        $subdomain  = $this->appointment->business->subdomain ?? null;
        $reviewPath = "/portal/appointments/{$this->appointment->id}/review";
        $reviewUrl  = ($baseDomain && $subdomain)
            ? "https://{$subdomain}.{$baseDomain}{$reviewPath}"
            : route('portal.appointments.review', $this->appointment);

        Mail::to($customer->email)->send(new ReviewRequestMail($this->appointment, $reviewUrl));
    }
}
