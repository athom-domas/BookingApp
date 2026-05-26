<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WaitlistController extends Controller
{
    public function index(Request $request): View
    {
        $entries = WaitlistEntry::where('user_id', $request->user()->id)
            ->whereIn('status', ['waiting', 'notified'])
            ->with('preferredStaff')
            ->latest()
            ->get();

        return view('portal.waitlist.index', ['entries' => $entries]);
    }

    public function create(Request $request): View
    {
        $services              = Service::active()->get();
        $staff                 = User::role('staff')->get();
        $prefilledServiceIds   = array_map('intval', (array) $request->query('service_ids', []));
        $prefilledStaffId      = $request->query('preferred_staff_id') ? (int) $request->query('preferred_staff_id') : null;

        return view('portal.waitlist.create', compact('services', 'staff', 'prefilledServiceIds', 'prefilledStaffId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_ids'         => ['required', 'array', 'min:1'],
            'service_ids.*'       => ['integer', 'exists:services,id'],
            'preferred_staff_id'  => ['nullable', 'exists:users,id'],
            'preferred_date_from' => ['required', 'date', 'after_or_equal:today'],
            'preferred_date_to'   => ['required', 'date', 'after_or_equal:preferred_date_from'],
            'preferred_time_from' => ['required', 'date_format:H:i'],
            'preferred_time_to'   => ['required', 'date_format:H:i', 'after:preferred_time_from'],
            'preferred_days'      => ['required', 'array', 'min:1'],
            'preferred_days.*'    => ['in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
        ]);

        WaitlistEntry::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status'  => 'waiting',
        ]);

        return redirect()->route('portal.waitlist.index')
            ->with('status', 'Iscritto alla lista d\'attesa.');
    }

    public function destroy(Request $request, WaitlistEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);
        $entry->update(['status' => 'cancelled']);

        return redirect()->route('portal.waitlist.index')
            ->with('status', 'Rimosso dalla lista d\'attesa.');
    }
}
