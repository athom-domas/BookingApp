<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;

class AppointmentActionController extends Controller
{
    public function confirm(Appointment $appointment)
    {
        if ($appointment->status === 'cancelled' || $appointment->isPast()) {
            return view('public.appointment-confirmed', ['appointment' => $appointment, 'alreadyPast' => true]);
        }

        $appointment->update(['customer_confirmed_at' => now()]);

        return view('public.appointment-confirmed', ['appointment' => $appointment, 'alreadyPast' => false]);
    }

    public function cancelForm(Appointment $appointment)
    {
        if (!$appointment->canBeCancelled()) {
            return view('public.appointment-cancelled', ['appointment' => $appointment, 'alreadyDone' => true]);
        }

        return view('public.appointment-cancel', ['appointment' => $appointment]);
    }

    public function processCancellation(Appointment $appointment, Request $request)
    {
        if ($appointment->canBeCancelled()) {
            $appointment->update([
                'status' => 'cancelled',
                'notes'  => $request->input('reason'),
            ]);
        }

        return view('public.appointment-cancelled', ['appointment' => $appointment, 'alreadyDone' => false]);
    }
}
