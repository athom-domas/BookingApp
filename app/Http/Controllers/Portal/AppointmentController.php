<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\LoyaltyAccount;
use App\Models\SystemSetting;
use App\Models\WaitlistEntry;
use App\Services\AppointmentService;
use App\Services\LoyaltyService;
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
            ->with(['staff.media', 'payment'])
            ->oldest('scheduled_date')
            ->get();

        $waitlistEntries = WaitlistEntry::where('user_id', $request->user()->id)
            ->whereIn('status', ['waiting', 'notified'])
            ->with('preferredStaff')
            ->latest()
            ->get();

        $loyaltyEnabled = SystemSetting::isLoyaltyEnabled();
        $loyaltyPoints = $loyaltyEnabled
            ? (LoyaltyAccount::where('user_id', $request->user()->id)->value('points') ?? 0)
            : 0;

        return view('portal.appointments.index', [
            'upcomingAppointments' => $appointments->filter(fn (Appointment $appointment) => $appointment->isUpcoming())->values(),
            'pastAppointments'     => $appointments->filter(fn (Appointment $appointment) => $appointment->isPast())->sortByDesc('scheduled_date')->values(),
            'waitlistEntries'      => $waitlistEntries,
            'loyaltyEnabled'       => $loyaltyEnabled,
            'loyaltyPoints'        => (int) $loyaltyPoints,
            'loyaltyThreshold'     => SystemSetting::getLoyaltyRewardThreshold(),
            'loyaltyPercentage'    => SystemSetting::getLoyaltyRewardPercentage(),
        ]);
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $this->authorizeAppointment($request, $appointment);

        return view('portal.appointments.show', [
            'appointment' => $appointment->load(['staff.media', 'payment']),
        ]);
    }

    public function payment(Request $request, Appointment $appointment): View|RedirectResponse
    {
        $this->authorizeAppointment($request, $appointment);

        $appointment->load(['staff.media', 'payment']);

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

        $payment = $appointment->payment;
        $loyaltyEnabled  = SystemSetting::isLoyaltyEnabled();
        $loyaltyEligible = false;
        $loyaltyPoints   = 0;

        if ($loyaltyEnabled && $payment->payment_method === 'stripe') {
            $loyaltyPoints   = (int) (LoyaltyAccount::where('user_id', $request->user()->id)->value('points') ?? 0);
            $loyaltyEligible = $loyaltyPoints >= SystemSetting::getLoyaltyRewardThreshold()
                || $payment->loyalty_discount_percentage !== null;
        }

        return view('portal.appointments.payment', [
            'appointment'               => $appointment,
            'payment'                   => $payment,
            'stripePublicKey'           => \App\Models\IntegrationSetting::getStripePublicKey() ?? config('services.stripe.public'),
            'clientSecret'              => $payment->stripe_response['client_secret'] ?? null,
            'loyaltyEnabled'            => $loyaltyEnabled,
            'loyaltyEligible'           => $loyaltyEligible,
            'loyaltyPoints'             => $loyaltyPoints,
            'loyaltyThreshold'          => SystemSetting::getLoyaltyRewardThreshold(),
            'loyaltyPercentage'         => SystemSetting::getLoyaltyRewardPercentage(),
            'discountApplied'           => $payment->loyalty_discount_percentage !== null,
            'discountedAmount'          => (float) $payment->amount,
            'originalAmount'            => (float) ($payment->loyalty_original_amount ?? $payment->amount),
        ]);
    }

    public function applyDiscount(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeAppointment($request, $appointment);

        $payment = $appointment->payment;

        if (! $payment || $payment->status !== 'pending' || $payment->payment_method !== 'stripe') {
            return redirect()->route('portal.appointments.payment', $appointment);
        }

        if ($payment->loyalty_discount_percentage !== null) {
            return redirect()->route('portal.appointments.payment', $appointment);
        }

        if (! SystemSetting::isLoyaltyEnabled()) {
            return redirect()->route('portal.appointments.payment', $appointment);
        }

        $account   = LoyaltyAccount::where('user_id', $request->user()->id)->first();
        $threshold = SystemSetting::getLoyaltyRewardThreshold();

        if (! $account || $account->points < $threshold) {
            return redirect()->route('portal.appointments.payment', $appointment)
                ->withErrors(['discount' => 'Punti insufficienti per applicare lo sconto.']);
        }

        $this->paymentService->applyLoyaltyDiscount(
            $payment,
            SystemSetting::getLoyaltyRewardPercentage(),
            (float) $payment->amount,
        );

        return redirect()->route('portal.appointments.payment', $appointment);
    }

    public function removeDiscount(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeAppointment($request, $appointment);

        $payment = $appointment->payment;

        if (! $payment || $payment->status !== 'pending' || $payment->loyalty_discount_percentage === null) {
            return redirect()->route('portal.appointments.payment', $appointment);
        }

        $this->paymentService->removeLoyaltyDiscount($payment);

        return redirect()->route('portal.appointments.payment', $appointment);
    }

    public function confirmPayment(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeAppointment($request, $appointment);

        $payment = $appointment->payment;

        // Riscatta i punti PRIMA di confermare il pagamento, così l'accredito avviene sull'importo netto.
        if ($payment && $payment->loyalty_discount_percentage !== null) {
            app(LoyaltyService::class)->redeem($appointment);
        }

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
