<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;
use Throwable;

class SocialAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        $state = base64_encode(json_encode([
            'b' => Business::currentId(),
            'h' => request()->getSchemeAndHttpHost(),
        ]));

        return Socialite::driver('google')
            ->stateless()
            ->with(['state' => $state])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $stateData = json_decode(base64_decode((string) $request->query('state', '')), true) ?? [];
        $businessId = (int) ($stateData['b'] ?? 0);
        $originalHost = isset($stateData['h']) && is_string($stateData['h']) ? $stateData['h'] : null;

        if ($businessId > 0 && ! app()->bound('current_business_id')) {
            app()->instance('current_business_id', $businessId);
        }

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (Throwable $e) {
            logger()->error('Google OAuth callback failed', ['error' => $e->getMessage(), 'class' => get_class($e)]);
            return $this->redirectToLogin($originalHost, 'Accesso con Google non riuscito. Riprova.');
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                if ($user->business_id !== Business::currentId()) {
                    return $this->redirectToLogin($originalHost, 'Il tuo account è registrato presso un altro salone. Accedi dal sito corretto.');
                }
                $user->update(['google_id' => $googleUser->getId()]);
            } else {
                try {
                    $user = User::create([
                        'name'        => $googleUser->getName(),
                        'email'       => $googleUser->getEmail(),
                        'google_id'   => $googleUser->getId(),
                        'password'    => null,
                        'business_id' => Business::currentId(),
                    ]);
                } catch (UniqueConstraintViolationException) {
                    return $this->redirectToLogin($originalHost, 'Accesso con Google non riuscito. Riprova.');
                }

                Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
                $user->assignRole('customer');
            }
        }

        $token = Str::random(64);
        Cache::put('google_auth_' . $token, $user->id, now()->addMinutes(5));

        $host = $originalHost ?? rtrim(config('app.url'), '/');
        return redirect($host . '/auth/google/exchange?token=' . $token);
    }

    public function exchange(Request $request): RedirectResponse
    {
        $token = (string) $request->query('token', '');
        $userId = Cache::pull('google_auth_' . $token);

        if (! $userId) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Link di accesso scaduto. Riprova con Google.']);
        }

        $user = User::findOrFail($userId);
        Auth::login($user, remember: true);
        $request->session()->regenerate();
        return redirect()->intended(route('portal.appointments.index'));
    }

    private function redirectToLogin(?string $originalHost, string $error): RedirectResponse
    {
        if ($originalHost) {
            return redirect(rtrim($originalHost, '/') . '/login')
                ->withErrors(['email' => $error]);
        }
        return redirect()->route('login')->withErrors(['email' => $error]);
    }
}
