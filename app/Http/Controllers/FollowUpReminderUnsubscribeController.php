<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowUpReminderUnsubscribeController extends Controller
{
    public function __invoke(Request $request, User $user): View
    {
        $user->preferences()->firstOrCreate(
            [],
            ['notification_channel' => 'email']
        )->update(['follow_up_reminders_enabled' => false]);

        return view('portal.follow-up-reminders.unsubscribed');
    }
}
