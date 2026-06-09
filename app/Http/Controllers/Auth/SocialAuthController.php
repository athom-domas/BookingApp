<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;
use Throwable;

class SocialAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Accesso con Google non riuscito. Riprova.']);
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user) {
            Auth::login($user, remember: true);
            return redirect()->intended(route('portal.appointments.index'));
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            $currentBusinessId = Business::currentId();

            if ($user->business_id !== $currentBusinessId) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Il tuo account è registrato presso un altro salone. Accedi dal sito corretto.']);
            }

            $user->update(['google_id' => $googleUser->getId()]);
            Auth::login($user, remember: true);
            return redirect()->intended(route('portal.appointments.index'));
        }

        $user = User::create([
            'name'        => $googleUser->getName(),
            'email'       => $googleUser->getEmail(),
            'google_id'   => $googleUser->getId(),
            'password'    => null,
            'business_id' => Business::currentId(),
        ]);

        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $user->assignRole('customer');

        Auth::login($user, remember: true);
        return redirect()->intended(route('portal.appointments.index'));
    }
}
