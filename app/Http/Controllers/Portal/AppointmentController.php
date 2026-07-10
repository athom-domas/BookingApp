<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\LoyaltyAccount;
use App\Models\SalonReview;
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
            ->with(['staff', 'payment'])
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

        $reviewedAppointmentIds = SalonReview::where('user_id', $request->user()->id)
            ->whereNotNull('appointment_id')
            ->pluck('appointment_id')
            ->all();

        $availableTiers = $loyaltyEnabled ? SystemSetting::getAvailableTiers((int) $loyaltyPoints) : [];
        $nextTier       = $loyaltyEnabled ? SystemSetting::getNextTier((int) $loyaltyPoints) : null;

        return view('portal.appointments.index', [
            'upcomingAppointments'   => $appointments->filter(fn(Appointment $appointment) => $appointment->isUpcoming())->values(),
            'pastAppointments'       => $appointments->filter(fn(Appointment $appointment) => $appointment->isPast())->sortByDesc('scheduled_date')->values(),
            'waitlistEntries'        => $waitlistEntries,
            'loyaltyEnabled'         => $loyaltyEnabled,
            'loyaltyPoints'          => (int) $loyaltyPoints,
            'loyaltyTiers'           => SystemSetting::getLoyaltyTiers(),
            'loyaltyAvailableTiers'  => $availableTiers,
            'loyaltyNextTier'        => $nextTier,
            'reviewedAppointmentIds' => $reviewedAppointmentIds,
        ]);
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $this->authorizeAppointment($request, $appointment);

        $showPreferencePrompt = false;
        $prefillPreferences   = null;

        if (auth()->check()) {
            $pref = \App\Models\UserPreference::where('user_id', auth()->id())
                ->where('business_id', app('current_business_id'))
                ->first();

            $noPreferences = ! $pref || empty($pref->preferred_days);
            $notDismissed  = ! $pref || ! $pref->booking_preference_prompt_dismissed;

            if ($noPreferences && $notDismissed) {
                $showPreferencePrompt = true;
                $dt      = $appointment->scheduled_date;
                $dow     = (int) $dt->format('w');
                $slotMin = (int) $dt->format('H') * 60 + (int) $dt->format('i');
                $fromMin = max(7 * 60, (int) (floor(($slotMin - 60) / 30) * 30));
                $toMin   = min(21 * 60, (int) (ceil(($slotMin + 60) / 30) * 30));

                $hour        = (int) $dt->format('H');
                $fasciaLabel = match (true) {
                    $hour < 12 => 'mattina',
                    $hour < 17 => 'pomeriggio',
                    default    => 'sera',
                };

                $dayNames = [0 => 'domenica', 1 => 'lunedì', 2 => 'martedì', 3 => 'mercoledì', 4 => 'giovedì', 5 => 'venerdì', 6 => 'sabato'];

                $prefillPreferences = [
                    'preferred_days'      => [$dow],
                    'preferred_time_from' => sprintf('%02d:%02d', intdiv($fromMin, 60), $fromMin % 60),
                    'preferred_time_to'   => sprintf('%02d:%02d', intdiv($toMin, 60), $toMin % 60),
                    'label'               => $dayNames[$dow] . ' ' . $fasciaLabel,
                ];
            }
        }

        return view('portal.appointments.show', [
            'appointment'          => $appointment->load(['staff', 'payment']),
            'showPreferencePrompt' => $showPreferencePrompt,
            'prefillPreferences'   => $prefillPreferences,
        ]);
    }

    public function payment(Request $request, Appointment $appointment): View|RedirectResponse
    {
        $this->authorizeAppointment($request, $appointment);

        $appointment->load(['staff', 'payment']);

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

        $pointsToEarn = $loyaltyEnabled
            ? (int) floor((float) $appointment->final_price * SystemSetting::getLoyaltyPointsPerEuro())
            : 0;

        $loyaltyPoints   = (int) (LoyaltyAccount::where('user_id', $request->user()->id)->value('points') ?? 0);
        $availableTiers = $loyaltyEnabled && $payment->payment_method === 'stripe'
            ? SystemSetting::getAvailableTiers($loyaltyPoints)
            : [];
        $loyaltyEligible = ! empty($availableTiers) || $payment->loyalty_discount_percentage !== null;

        return view('portal.appointments.payment', [
            'appointment'               => $appointment,
            'payment'                   => $payment,
            'stripePublicKey'           => \App\Models\IntegrationSetting::getStripePublicKey() ?? config('services.stripe.public'),
            'clientSecret'              => $payment->stripe_response['client_secret'] ?? null,
            'stripeAccountId'           => $payment->stripe_account_id,
            'loyaltyEnabled'            => $loyaltyEnabled,
            'loyaltyEligible'           => $loyaltyEligible,
            'loyaltyPoints'             => $loyaltyPoints,
            'loyaltyAvailableTiers'     => $availableTiers,
            'loyaltyPointsDispl'        => $payment->loyalty_tier_threshold,
            'discountApplied'           => $payment->loyalty_discount_percentage !== null,
            'discountedAmount'          => (float) $payment->amount,
            'originalAmount'            => (float) ($payment->loyalty_original_amount ?? $payment->amount),
            'pointsToEarn'              => $pointsToEarn,
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

        $account = LoyaltyAccount::where('user_id', $request->user()->id)->first();
        if (! $account) {
            return redirect()->route('portal.appointments.payment', $appointment)
                ->withErrors(['discount' => 'Nessun punto fedeltà disponibile.']);
        }

        $threshold = $request->integer('threshold');
        $tiers    = SystemSetting::getAvailableTiers($account->points);

        $tier = collect($tiers)->firstWhere('threshold', $threshold);
        if (! $tier) {
            return redirect()->route('portal.appointments.payment', $appointment)
                ->withErrors(['discount' => 'Sconto non disponibile.']);
        }

        $this->paymentService->applyLoyaltyDiscount(
            $payment,
            (int) ($tier['percentage'] ?? 0),
            (float) $payment->amount,
            isset($tier['amount']) ? (float) $tier['amount'] : null,
            (int) $tier['threshold'],
            true,
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
        // Se redeem() ritorna 0 (es. soglia alzata dall'admin dopo che il cliente aveva applicato lo sconto),
        // ripristina l'importo originale sul Stripe PI e blocca il pagamento con un avviso.
        if ($payment && $payment->loyalty_discount_percentage !== null) {
            $tier = null;
            if ($payment->loyalty_tier_threshold) {
                $account = LoyaltyAccount::where('user_id', $request->user()->id)->first();
                if ($account) {
                    $tiers = SystemSetting::getAvailableTiers($account->points);
                    $tier  = collect($tiers)->firstWhere('threshold', $payment->loyalty_tier_threshold);
                }
            }
            $redeemed = app(LoyaltyService::class)->redeem($appointment, $tier);
            if (($redeemed['percentage'] ?? 0) === 0 && ($redeemed['amount'] ?? 0) === 0) {
                $this->paymentService->removeLoyaltyDiscount($payment);

                return back()->withErrors([
                    'payment' => 'Le condizioni del programma fedeltà sono cambiate. Lo sconto è stato rimosso. Verifica il nuovo importo e procedi al pagamento.',
                ]);
            }
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
