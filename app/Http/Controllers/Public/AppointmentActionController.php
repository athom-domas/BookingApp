<?php

namespace App\Http\Controllers\Public;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppointmentActionController extends Controller
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    public function confirm(Appointment $appointment, Request $request)
    {
        abort_unless((int) $request->query('uid') === $appointment->user_id, 403);

        if ($appointment->status === 'cancelled' || $appointment->isPast()) {
            return view('public.appointment-confirmed', ['appointment' => $appointment, 'alreadyPast' => true]);
        }

        $appointment->update(['customer_confirmed_at' => now()]);

        return view('public.appointment-confirmed', ['appointment' => $appointment, 'alreadyPast' => false]);
    }

    public function cancelForm(Appointment $appointment, Request $request)
    {
        abort_unless((int) $request->query('uid') === $appointment->user_id, 403);

        if (! $appointment->canBeCancelled()) {
            return view('public.appointment-cancelled', ['appointment' => $appointment, 'alreadyDone' => true]);
        }

        return view('public.appointment-cancel', ['appointment' => $appointment]);
    }

    public function paymentPortal(Appointment $appointment, Request $request): RedirectResponse
    {
        abort_unless((int) $request->query('uid') === $appointment->user_id, 403);

        Auth::login(User::findOrFail($appointment->user_id));

        return redirect()->route('portal.appointments.payment', $appointment);
    }

    public function processCancellation(Appointment $appointment, Request $request)
    {
        abort_unless((int) $request->query('uid') === $appointment->user_id, 403);

        try {
            $this->appointmentService->cancelAppointment($appointment->id, $request->input('reason'));
        } catch (BookingException) {
            // canBeCancelled check or 24h window — treat as already done
        }

        $fresh = $appointment->fresh();

        return view('public.appointment-cancelled', [
            'appointment' => $fresh,
            'alreadyDone' => false,
            'refunded'    => $fresh->payment?->status === 'refunded',
        ]);
    }
}
