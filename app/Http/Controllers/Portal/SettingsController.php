<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityRule;
use App\Models\UserPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $preferences = UserPreference::firstOrCreate(
            ['user_id' => $request->user()->id, 'business_id' => app('current_business_id')],
            ['notification_channel' => 'email']
        );

        if ($preferences->preferred_time_from) {
            $preferences->preferred_time_from = substr($preferences->preferred_time_from, 0, 5);
        }
        if ($preferences->preferred_time_to) {
            $preferences->preferred_time_to = substr($preferences->preferred_time_to, 0, 5);
        }

        $openDayNums = AvailabilityRule::where('is_available', true)
            ->distinct()->pluck('day_of_week')->sort()->values()->all();

        return view('portal.settings.index', [
            'user'        => $request->user(),
            'preferences' => $preferences,
            'openDayNums' => $openDayNums,
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
        ], [
            'name.required'                   => 'Il nome è obbligatorio.',
            'name.max'                        => 'Il nome non può superare 255 caratteri.',
            'email.required'                  => 'L\'email è obbligatoria.',
            'email.email'                     => 'Inserisci un indirizzo email valido.',
            'email.unique'                    => 'Questo indirizzo email è già in uso.',
            'current_password.required_with'  => 'La password attuale è obbligatoria per impostarne una nuova.',
            'current_password.current_password' => 'La password attuale non è corretta.',
            'new_password.min'                => 'La nuova password deve essere di almeno 8 caratteri.',
            'new_password.confirmed'          => 'Le password non coincidono.',
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
            'notification_channel' => ['required', 'in:email,whatsapp'],
            'phone_number'         => [
                'nullable',
                'required_if:notification_channel,whatsapp',
                'regex:/^\d{10}$/',
            ],
        ], [
            'notification_channel.required' => 'Seleziona un canale di notifica.',
            'notification_channel.in'       => 'Il canale di notifica selezionato non è valido.',
            'phone_number.required_if'      => 'Il numero di telefono è obbligatorio per il canale selezionato.',
            'phone_number.regex'            => 'Inserisci un numero italiano valido (10 cifre, es. 3341234567).',
        ]);

        if (!empty($validated['phone_number'])) {
            $digits = preg_replace('/\D/', '', $validated['phone_number']);
            $validated['phone_number'] = '+39' . $digits;
        }

        $request->user()->preferences()->firstOrCreate([])->update($validated);

        return back()->with('notifications_updated', 'Preferenze notifiche aggiornate.');
    }

    public function updateCommunications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'follow_up_reminders_enabled' => ['nullable', 'boolean'],
        ]);

        $request->user()->preferences()->firstOrCreate(
            [],
            ['notification_channel' => 'email']
        )->update([
            'follow_up_reminders_enabled' => $validated['follow_up_reminders_enabled'] ?? false,
        ]);

        return back()->with('communications_updated', 'Preferenze aggiornate.');
    }

    public function updateBookingPreferences(Request $request): RedirectResponse
    {
        $openDays = AvailabilityRule::where('is_available', true)
            ->distinct()->pluck('day_of_week')->all();

        $validated = $request->validate([
            'preferred_days'      => ['nullable', 'array'],
            'preferred_days.*'    => ['integer', Rule::in($openDays ?: range(0, 6))],
            'preferred_time_from' => ['nullable', 'date_format:H:i', 'required_with:preferred_time_to'],
            'preferred_time_to'   => [
                'nullable', 'date_format:H:i',
                'required_with:preferred_time_from',
                'after:preferred_time_from',
            ],
        ]);

        UserPreference::firstOrCreate(
            ['user_id' => $request->user()->id, 'business_id' => app('current_business_id')],
            ['notification_channel' => 'email']
        )->update([
            'preferred_days'      => $validated['preferred_days'] ?? null,
            'preferred_time_from' => $validated['preferred_time_from'] ?? null,
            'preferred_time_to'   => $validated['preferred_time_to'] ?? null,
        ]);

        return back()->with('status', 'Preferenze di prenotazione aggiornate.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $openDays = AvailabilityRule::where('is_available', true)
            ->distinct()->pluck('day_of_week')->all();

        $validated = $request->validate([
            'notification_channel'        => ['required', 'in:email,whatsapp'],
            'phone_number'                => ['nullable', 'required_if:notification_channel,whatsapp', 'regex:/^\d{10}$/'],
            'follow_up_reminders_enabled' => ['nullable', 'boolean'],
            'preferred_days'              => ['nullable', 'array'],
            'preferred_days.*'            => ['integer', Rule::in($openDays ?: range(0, 6))],
            'preferred_time_from'         => ['nullable', 'date_format:H:i', 'required_with:preferred_time_to'],
            'preferred_time_to'           => ['nullable', 'date_format:H:i', 'required_with:preferred_time_from', 'after:preferred_time_from'],
        ], [
            'notification_channel.required' => 'Seleziona un canale di notifica.',
            'phone_number.required_if'      => 'Il numero di telefono è obbligatorio per il canale WhatsApp.',
            'phone_number.regex'            => 'Inserisci un numero italiano valido (10 cifre, es. 3341234567).',
        ]);

        if (! empty($validated['phone_number'])) {
            $validated['phone_number'] = '+39' . preg_replace('/\D/', '', $validated['phone_number']);
        }

        UserPreference::firstOrCreate(
            ['user_id' => $request->user()->id, 'business_id' => app('current_business_id')],
            ['notification_channel' => 'email']
        )->update([
            'notification_channel'        => $validated['notification_channel'],
            'phone_number'                => $validated['phone_number'] ?? null,
            'follow_up_reminders_enabled' => $validated['follow_up_reminders_enabled'] ?? false,
            'preferred_days'              => $validated['preferred_days'] ?? null,
            'preferred_time_from'         => $validated['preferred_time_from'] ?? null,
            'preferred_time_to'           => $validated['preferred_time_to'] ?? null,
        ]);

        return back()->with('preferences_updated', 'Preferenze aggiornate.');
    }

    public function dismissBookingPreferencePrompt(Request $request): RedirectResponse
    {
        UserPreference::firstOrCreate(
            ['user_id' => $request->user()->id, 'business_id' => app('current_business_id')],
            ['notification_channel' => 'email']
        )->update(['booking_preference_prompt_dismissed' => true]);

        return back();
    }
}
