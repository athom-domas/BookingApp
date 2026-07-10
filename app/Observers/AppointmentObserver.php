<?php

namespace App\Observers;

use App\Events\AppointmentCancelled;
use App\Jobs\SendReviewRequestJob;
use App\Models\Appointment;
use App\Models\FollowUpReminder;
use App\Models\SystemSetting;
use App\Services\LoyaltyService;
use Illuminate\Support\Facades\Cache;

class AppointmentObserver
{
    public function created(Appointment $appointment): void
    {
        $this->bustDateCache($appointment->business_id, $appointment->scheduled_date->format('Y-m'));
    }

    public function updated(Appointment $appointment): void
    {
        if ($appointment->wasChanged('scheduled_date')) {
            $this->bustDateCache($appointment->business_id, $appointment->scheduled_date->format('Y-m'));
            $old = $appointment->getOriginal('scheduled_date');
            if ($old) {
                $this->bustDateCache($appointment->business_id, substr($old, 0, 7));
            }
        } elseif ($appointment->wasChanged('status')) {
            $this->bustDateCache($appointment->business_id, $appointment->scheduled_date->format('Y-m'));
        }

        if (! $appointment->wasChanged('status')) {
            return;
        }

        if ($appointment->status === 'cancelled') {
            $byAdmin = auth()->check() && auth()->user()?->isAdmin();
            AppointmentCancelled::dispatch($appointment->fresh(), null, $byAdmin);
            $this->reverse($appointment);
        } elseif ($appointment->status === 'completed') {
            $this->accrue($appointment);
            $this->scheduleReviewRequest($appointment);
            $this->scheduleFollowUpReminder($appointment);
        }
    }

    private function scheduleReviewRequest(Appointment $appointment): void
    {
        if (! app()->bound('current_business_id')) {
            app()->instance('current_business_id', $appointment->business_id);
        }

        if (! SystemSetting::isReviewRequestEnabled()) {
            return;
        }

        $delay = SystemSetting::getReviewRequestDelayHours();

        SendReviewRequestJob::dispatch($appointment)->delay(now()->addHours($delay));
    }

    private function accrue(Appointment $appointment): void
    {
        $price = (float) ($appointment->loyalty_discounted_price ?? $appointment->final_price ?? 0);
        if ($price <= 0) {
            return;
        }

        if (! app()->bound('current_business_id')) {
            app()->instance('current_business_id', $appointment->business_id);
        }

        app(LoyaltyService::class)->accrue($appointment, $price);
    }

    private function reverse(Appointment $appointment): void
    {
        if (! app()->bound('current_business_id')) {
            app()->instance('current_business_id', $appointment->business_id);
        }

        app(LoyaltyService::class)->reverse($appointment);

        $payment = $appointment->payment;
        if ($payment && $payment->status === 'pending') {
            app(\App\Services\PaymentService::class)->cancelPendingPayment($payment);
        }
    }

    private function bustDateCache(int $businessId, string $month): void
    {
        Cache::increment("booking_dates_v:{$businessId}:{$month}");
    }

    private function scheduleFollowUpReminder(Appointment $appointment): void
    {
        if (! app()->bound('current_business_id')) {
            app()->instance('current_business_id', $appointment->business_id);
        }

        if (! SystemSetting::isFollowUpRemindersEnabled()) {
            return;
        }

        if (! $appointment->user_id) {
            return;
        }

        $prefs = $appointment->user->preferences;

        if (! $prefs || ! $prefs->follow_up_reminders_enabled) {
            return;
        }

        $hasFutureAppointment = Appointment::where('user_id', $appointment->user_id)
            ->where('business_id', $appointment->business_id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('scheduled_date', '>', now())
            ->exists();

        if ($hasFutureAppointment) {
            return;
        }

        $existsForAppointment = FollowUpReminder::where('appointment_id', $appointment->id)
            ->where('type', 'rebooking')
            ->whereIn('status', ['pending', 'processing', 'sent'])
            ->exists();

        if ($existsForAppointment) {
            return;
        }

        $existsForUser = FollowUpReminder::where('business_id', $appointment->business_id)
            ->where('user_id', $appointment->user_id)
            ->where('type', 'rebooking')
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        if ($existsForUser) {
            return;
        }

        $days = SystemSetting::getFollowUpReminderDays();

        FollowUpReminder::create([
            'business_id'    => $appointment->business_id,
            'user_id'        => $appointment->user_id,
            'appointment_id' => $appointment->id,
            'type'           => 'rebooking',
            'delay_days'     => $days,
            'scheduled_for'  => now()->addDays($days),
            'status'         => 'pending',
        ]);
    }
}
