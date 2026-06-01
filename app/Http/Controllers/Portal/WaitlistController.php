<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WaitlistController extends Controller
{
    public function create(Request $request): View
    {
        $services            = Service::active()->get();
        $staff               = User::role('staff')->where('business_id', app('current_business_id'))->get();
        $prefilledServiceIds = array_map('intval', (array) $request->query('service_ids', []));
        $prefilledStaffId    = $request->query('preferred_staff_id') ? (int) $request->query('preferred_staff_id') : null;
        $prefilledDays       = array_values(array_filter((array) $request->query('preferred_days', [])));
        $prefilledTimeFrom   = $request->query('preferred_time_from', '');
        $prefilledTimeTo     = $request->query('preferred_time_to', '');

        $openDayNums = AvailabilityRule::where('is_available', true)
            ->distinct()
            ->pluck('day_of_week')
            ->sort()
            ->values()
            ->all();

        return view('portal.waitlist.create', compact(
            'services', 'staff', 'prefilledServiceIds', 'prefilledStaffId',
            'prefilledDays', 'prefilledTimeFrom', 'prefilledTimeTo', 'openDayNums'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_ids'         => ['required', 'array', 'min:1'],
            'service_ids.*'       => ['integer', 'exists:services,id'],
            'preferred_staff_id'  => ['nullable', 'exists:users,id'],
            'preferred_time_from' => ['required', 'date_format:H:i'],
            'preferred_time_to'   => ['required', 'date_format:H:i', 'after:preferred_time_from'],
            'preferred_days'      => ['required', 'array', 'min:1'],
            'preferred_days.*'    => ['date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        WaitlistEntry::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status'  => 'waiting',
        ]);

        return redirect()->route('portal.appointments.index')
            ->with('status', 'Iscritto alla lista d\'attesa.');
    }

    public function destroy(Request $request, WaitlistEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);
        $entry->update(['status' => 'cancelled']);

        return redirect()->route('portal.appointments.index')
            ->with('status', 'Rimosso dalla lista d\'attesa.');
    }
}
