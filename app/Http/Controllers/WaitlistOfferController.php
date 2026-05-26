<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Models\WaitlistEntry;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\View\View;

class WaitlistOfferController extends Controller
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    public function accept(WaitlistEntry $entry): View
    {
        if ($entry->status !== 'notified' || now()->isAfter($entry->offer_expires_at)) {
            $entry->update(['status' => 'expired']);

            return view('portal.waitlist.offer-expired');
        }

        $slot          = $entry->offered_slot;
        $scheduledDate = Carbon::parse($slot['date'] . ' ' . $slot['time']);

        try {
            $appointment = $this->appointmentService->bookAppointment(
                $entry->user_id,
                $entry->service_ids,
                $slot['staff_id'],
                $scheduledDate,
            );

            $entry->update(['status' => 'booked']);

            return view('portal.waitlist.offer-accepted', ['appointment' => $appointment]);

        } catch (BookingException) {
            $entry->update(['status' => 'expired']);

            return view('portal.waitlist.offer-expired');
        }
    }
}
