<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('contact');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'email'   => 'required|email|max:150',
            'phone'   => 'nullable|string|max:30',
            'subject' => 'required|string|in:demo,info,support,other',
            'message' => 'required|string|max:3000',
        ], [
            'name.required'    => 'Inserisci il tuo nome.',
            'name.max'         => 'Il nome non può superare 100 caratteri.',
            'email.required'   => 'Inserisci il tuo indirizzo email.',
            'email.email'      => 'L\'indirizzo email non è valido.',
            'email.max'        => 'L\'email non può superare 150 caratteri.',
            'phone.max'        => 'Il numero di telefono non può superare 30 caratteri.',
            'subject.required' => 'Seleziona il tipo di richiesta.',
            'subject.in'       => 'Seleziona un tipo di richiesta valido.',
            'message.required' => 'Scrivi il tuo messaggio.',
            'message.max'      => 'Il messaggio non può superare 3000 caratteri.',
        ]);

        Mail::to(config('mail.contact_address', config('mail.from.address')))
            ->send(new ContactFormMail($data));

        return redirect()->route('contact')
            ->with('success', 'Messaggio ricevuto! Ti risponderemo entro 24 ore nei giorni lavorativi.');
    }
}
