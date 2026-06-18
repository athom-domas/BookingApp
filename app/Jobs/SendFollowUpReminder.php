<?php

namespace App\Jobs;

use App\Mail\FollowUpReminderMail;
use App\Models\Appointment;
use App\Models\FollowUpReminder;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendFollowUpReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $reminderId) {}

    public function handle(): void
    {
        $claimed = FollowUpReminder::whereKey($this->reminderId)
            ->where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->update(['status' => 'processing', 'processing_at' => now()]);

        if (! $claimed) {
            return;
        }

        $reminder = FollowUpReminder::findOrFail($this->reminderId);

        app()->instance('current_business_id', $reminder->business_id);

        if (! SystemSetting::isFollowUpRemindersEnabled()) {
            $reminder->update(['status' => 'skipped', 'skipped_reason' => 'feature_disabled']);
            return;
        }

        $prefs = $reminder->user->preferences;

        if (! $prefs || ! $prefs->follow_up_reminders_enabled) {
            $reminder->update(['status' => 'skipped', 'skipped_reason' => 'user_disabled']);
            return;
        }

        $hasFutureAppointment = Appointment::where('user_id', $reminder->user_id)
            ->where('business_id', $reminder->business_id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('scheduled_date', '>', now())
            ->exists();

        if ($hasFutureAppointment) {
            $reminder->update(['status' => 'skipped', 'skipped_reason' => 'user_has_future_appointment']);
            return;
        }

        $latestCompleted = Appointment::where('user_id', $reminder->user_id)
            ->where('business_id', $reminder->business_id)
            ->where('status', 'completed')
            ->orderByDesc('scheduled_date')
            ->first();

        if (
            $latestCompleted &&
            $reminder->appointment_id !== null &&
            $latestCompleted->id !== $reminder->appointment_id &&
            $latestCompleted->scheduled_date->gt(now()->subDays($reminder->delay_days))
        ) {
            $reminder->update(['status' => 'skipped', 'skipped_reason' => 'recent_appointment_completed']);
            return;
        }

        try {
            Mail::to($reminder->user->email)->send(new FollowUpReminderMail($reminder));
            $reminder->update(['status' => 'sent', 'sent_at' => now(), 'channel' => 'email']);
        } catch (\Throwable $e) {
            $reminder->update([
                'status'        => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);
        }
    }
}
