<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Le credenziali inserite non sono corrette.']);
        }

        $request->session()->regenerate();

        $user = Auth::user();
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
