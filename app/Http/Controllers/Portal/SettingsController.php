<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $preferences = $request->user()->preferences()->firstOrCreate([], [
            'notification_channel' => 'email',
        ]);

        return view('portal.settings.index', [
            'user' => $request->user(),
            'preferences' => $preferences,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'current_password' => ['nullable', 'required_with:new_password', 'current_password'],
            'new_password'     => ['nullable', Password::min(8), 'confirmed'],
        ]);

        $user->name  = $validated['name'];
        $user->email = $validated['email'];

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if (!empty($validated['new_password'])) {
            $user->password = Hash::make($validated['new_password']);
        }

        $user->save();

        return back()->with('profile_updated', 'Profilo aggiornato.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notification_channel' => ['required', 'in:email,sms,whatsapp'],
            'phone_number'         => [
                'nullable',
                'required_if:notification_channel,sms',
                'required_if:notification_channel,whatsapp',
                'regex:/^\d{8,12}$/',
                'max:12',
            ],
        ]);

        if (!empty($validated['phone_number'])) {
            $validated['phone_number'] = '+39' . $validated['phone_number'];
        }

        $request->user()->preferences()->firstOrCreate([])->update($validated);

        return back()->with('notifications_updated', 'Preferenze notifiche aggiornate.');
    }
}
