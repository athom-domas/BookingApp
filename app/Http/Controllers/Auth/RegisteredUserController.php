<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $return = $request->string('return')->toString();
        if ($return !== '' && str_starts_with($return, '/') && !str_starts_with($return, '//')) {
            session()->put('url.intended', $return);
        }

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = app()->bound('current_business_id') ? Business::currentId() : null;

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users')->where('business_id', $businessId)],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'password'    => $validated['password'],
            'business_id' => $businessId,
        ]);

        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $user->assignRole('customer');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('portal.appointments.index'));
    }
}
