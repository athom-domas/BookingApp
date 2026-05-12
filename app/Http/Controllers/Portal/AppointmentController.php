<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly PaymentService $paymentService,
    ) {}

    public function dashboard(): RedirectResponse
    {
        return redirect()->route('portal.appointments.index');
    }

    public function index(Request $request): View
    {
        $appointments = Appointment::where('user_id', $request->user()->id)
            ->with(['service', 'staff', 'payment'])
            ->latest('scheduled_date')
            ->get();

        return view('portal.appointments.index', [
            'upcomingAppointments' => $appointments->filter(fn (Appointment $appointment) => $appointment->isUpcoming())->values(),
            'pastAppointments' => $appointments->filter(fn (Appointment $appointment) => $appointment->isPast())->values(),
        ]);
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $this->authorizeAppointment($request, $appointment);

        return view('portal.appointments.show', [
            'appointment' => $appointment->load(['service', 'staff', 'payment']),
        ]);
    }

    public function payment(Request $request, Appointment $appointment): View|RedirectResponse
    {
        $this->authorizeAppointment($request, $appointment);

        $appointment->load(['service', 'staff', 'payment']);

        if (! $appointment->payment) {
            return redirect()
                ->route('portal.appointments.show', $appointment)
                ->withErrors(['payment' => 'Nessun pagamento trovato per questa prenotazione.']);
        }

        if ($appointment->payment->status === 'completed') {
            return redirect()
                ->route('portal.appointments.show', $appointment)
                ->with('status', 'Pagamento già completato.');
        }

        return view('portal.appointments.payment', [
            'appointment' => $appointment,
            'payment' => $appointment->payment,
            'stripePublicKey' => config('services.stripe.public'),
            'clientSecret' => $appointment->payment->stripe_response['client_secret'] ?? null,
        ]);
    }

    public function confirmPayment(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeAppointment($request, $appointment);

        try {
            $this->paymentService->confirmPayment($appointment->id);
        } catch (BookingException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()
            ->route('portal.appointments.show', $appointment)
            ->with('status', 'Pagamento completato e prenotazione confermata.');
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeAppointment($request, $appointment);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->appointmentService->cancelAppointment($appointment->id, $validated['reason'] ?? null);
        } catch (BookingException $e) {
            return back()->withErrors(['appointment' => $e->getMessage()]);
        }

        return redirect()
            ->route('portal.appointments.index')
            ->with('status', 'Prenotazione cancellata.');
    }

    private function authorizeAppointment(Request $request, Appointment $appointment): void
    {
        abort_unless($appointment->user_id === $request->user()->id, 403);
    }
}
