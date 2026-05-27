<?php

namespace App\Http\Controllers;

use App\Models\WaitlistEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WaitlistOfferController extends Controller
{
    public function accept(WaitlistEntry $entry): View|RedirectResponse
    {
        if ($entry->status !== 'notified') {
            return view('portal.waitlist.offer-expired');
        }

        $slot = $entry->offered_slot;

        session()->flash('bookingWizardPrefill', [
            'selectedServiceIds' => $entry->service_ids,
            'staffId'            => $slot['staff_id'],
            'date'               => $slot['date'],
            'slot'               => $slot['time'],
            'calendarMonth'      => substr($slot['date'], 0, 7),
            'paymentMethod'      => null,
            'notes'              => '',
            'step'               => 4,
            'completed'          => [1, 2, 3],
            'waitlistEntryId'    => $entry->id,
        ]);

        return redirect()->route('booking.create');
    }
}
