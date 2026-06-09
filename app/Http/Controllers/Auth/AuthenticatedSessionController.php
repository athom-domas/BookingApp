<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View
    {
        $return = $request->string('return')->toString();
        if ($return !== '' && str_starts_with($return, '/') && !str_starts_with($return, '//')) {
            session()->put('url.intended', $return);
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])
            ->when(app()->bound('current_business_id'), fn ($q) => $q->where('business_id', app('current_business_id')))
            ->first();

        if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Le credenziali inserite non sono corrette.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $default = ($user->isAdmin() || $user->isStaff())
            ? '/admin'
            : route('portal.appointments.index');

        return redirect()->intended($default);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('booking.index');
    }
}
