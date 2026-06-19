<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Inserisci il tuo indirizzo email.',
            'email.email'    => 'Inserisci un indirizzo email valido.',
        ]);

        if (! User::where('email', $request->email)->exists()) {
            return back()->withErrors(['email' => 'Nessun account trovato con questa email.'])->withInput();
        }

        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Ti abbiamo inviato il link per reimpostare la password. Controlla la tua casella email.');
    }
}
