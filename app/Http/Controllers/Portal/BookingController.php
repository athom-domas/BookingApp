<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreBookingRequest;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;
use App\Models\SalonReview;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\WaitlistEntry;
use App\Events\AppointmentConfirmed;
use App\Services\Booking\AppointmentService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(): View
    {
        if (! app()->bound('current_business_id')) {
            return view('landing');
        }

        $businessId = app('current_business_id');
        $business   = Business::find($businessId);
        $profile    = SalonProfile::current()->load('media');

        $hasAnyBlocks = BusinessPageBlock::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->exists();

        if (! $hasAnyBlocks) {
            Log::warning('page-builder: business has no blocks, rendering legacy', [
                'business_id' => $businessId,
            ]);
            $services = Service::active()->orderBy('sort_order')->orderBy('name')->get();
            $staff    = User::whereHas('roles', fn($q) => $q->where('name', 'staff')->where('guard_name', 'web'))
                ->where('business_id', $businessId)
                ->where(
                    fn($q) => $q
                        ->whereNotNull('bio')
                        ->orWhereNotNull('avatar_path')
                )
                ->orderByRaw($this->staffOrderRaw())
                ->orderBy('sort_order')
                ->get();
            $reviews = SystemSetting::isReviewsEnabled()
                ? SalonReview::published()->ordered()->get()
                : collect();

            return view('welcome-legacy', compact('profile', 'services', 'staff', 'reviews'));
        }

        $blocks = BusinessPageBlock::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get();

        return view('welcome', compact('business', 'blocks', 'profile'));
    }

    public function create(Request $request): View
    {
        $businessId = app('current_business_id');

        $services = Service::active()
            ->with(['staff' => fn($q) => $q
                ->whereHas('roles', fn($r) => $r->where('name', 'staff')->where('guard_name', 'web'))
                ->where('business_id', $businessId)
                ->orderByRaw($this->staffOrderRaw())
                ->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = ServiceCategory::active()
            ->whereHas('services', fn ($q) => $q->where('active', true))
            ->orderBy('sort_order')
            ->get();

        $staff = User::whereHas('roles', fn($q) => $q->where('name', 'staff')->where('guard_name', 'web'))
            ->where('business_id', $businessId)
            ->whereHas('services', fn($q) => $q->active())
            ->with(['services' => fn($q) => $q->active()->select('services.id', 'services.name')])
            ->orderByRaw($this->staffOrderRaw())
            ->orderBy('sort_order')
            ->get();

        $wizardPrefill = session('bookingWizardPrefill');
        if (! $wizardPrefill && $request->filled('service') && is_numeric($request->query('service'))) {
            $serviceId = (int) $request->query('service');
            if ($services->contains('id', $serviceId)) {
                $wizardPrefill = ['selectedServiceIds' => [$serviceId]];
            }
        }
        if (! $wizardPrefill && $request->has('service_ids')) {
            $serviceIds = array_values(array_filter(
                array_map('intval', (array) $request->query('service_ids')),
                fn($id) => $services->contains('id', $id),
            ));
            if ($serviceIds) {
                $staffId = $request->filled('preferred_staff_id') && is_numeric($request->query('preferred_staff_id'))
                    ? (int) $request->query('preferred_staff_id')
                    : null;
                if ($staffId && ! $staff->contains('id', $staffId)) {
                    $staffId = null;
                }
                $wizardPrefill = ['selectedServiceIds' => $serviceIds, 'staffId' => $staffId, 'step' => 3, 'completed' => [1, 2]];
            }
        }

        $bookingPreferences = null;
        if (auth()->check()) {
            $pref = UserPreference::where('user_id', auth()->id())
                ->where('business_id', $businessId)
                ->first();
            $hasPrefs = $pref && (! empty($pref->preferred_days) || $pref->preferred_time_from);
            if ($hasPrefs) {
                $bookingPreferences = [
                    'days'     => $pref->preferred_days ?? [],
                    'timeFrom' => $pref->preferred_time_from ? substr($pref->preferred_time_from, 0, 5) : null,
                    'timeTo'   => $pref->preferred_time_to   ? substr($pref->preferred_time_to,   0, 5) : null,
                ];
            }
        }

        return view('portal.booking.index', [
            'services'           => $services,
            'staff'              => $staff,
            'categories'         => $categories,
            'wizardPrefill'      => $wizardPrefill,
            'paymentMode'        => $this->resolvePaymentMode(),
            'bookingPreferences' => $bookingPreferences,
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $pendingAppointment = Appointment::where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->first();

        if ($pendingAppointment) {
            if ($request->input('payment_method') === 'in_salon') {
                $pendingAppointment->payment()
                    ->where('status', 'pending')
                    ->delete();
                $pendingAppointment->update(['status' => 'confirmed']);
                AppointmentConfirmed::dispatch($pendingAppointment->fresh(), byAdmin: false);

                return redirect()
                    ->route('portal.appointments.show', $pendingAppointment)
                    ->with('status', 'Prenotazione confermata. Ci vediamo in salone!');
            }

            return back()->withInput()->withErrors([
                'booking' => 'Hai una prenotazione in attesa di pagamento. Completala o annullala prima di prenotarne una nuova.',
            ]);
        }

        try {
            $appointment = $this->appointmentService->bookDirect([
                'userId'             => $request->user()->id,
                'serviceIds'         => $request->input('service_ids'),
                'staffId'            => $request->filled('staff_id') ? $request->integer('staff_id') : null,
                'scheduledDate'      => Carbon::parse($request->string('scheduled_date')),
                'confirmImmediately' => $request->input('payment_method') === 'in_salon',
                'notes'              => $request->input('notes'),
            ]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['scheduled_date' => $e->getMessage()]);
        }

        if ($waitlistEntryId = $request->integer('waitlist_entry_id') ?: null) {
            $waitlistEntry = WaitlistEntry::where('id', $waitlistEntryId)
                ->where('user_id', $request->user()->id)
                ->first();
            if ($waitlistEntry && $waitlistEntry->status === 'notified') {
                $slot = $waitlistEntry->offered_slot;
                $waitlistEntry->update(['status' => 'booked']);
                WaitlistEntry::where('status', 'notified')
                    ->where('id', '!=', $waitlistEntry->id)
                    ->where('offered_slot->date', $slot['date'])
                    ->where('offered_slot->time', $slot['time'])
                    ->where('offered_slot->staff_id', $slot['staff_id'])
                    ->update(['status' => 'waiting', 'offered_slot' => null]);
            }
        }

        if ($request->input('payment_method') === 'in_salon') {
            return redirect()
                ->route('portal.appointments.show', $appointment)
                ->with('status', 'Prenotazione confermata. Ci vediamo in salone!');
        }

        $amountCents = (int) round((float) $appointment->final_price * 100);
        $this->paymentService->initiateStripePayment($appointment->id, $amountCents);

        return redirect()
            ->route('portal.appointments.payment', $appointment)
            ->with('status', 'Prenotazione creata. Completa il pagamento per confermarla.');
    }

    private function resolvePaymentMode(): string
    {
        $configured = SystemSetting::getPaymentMode();
        if ($configured === 'in_salon') {
            return 'in_salon';
        }
        $business = Business::find(app()->bound('current_business_id') ? app('current_business_id') : null);
        if (! $business || ! $business->canAcceptOnlinePayments()) {
            return 'in_salon';
        }
        return $configured;
    }

    private function staffOrderRaw(): string
    {
        return "CASE WHEN EXISTS (
            SELECT 1 FROM model_has_roles mhr
            JOIN roles r ON r.id = mhr.role_id
            WHERE mhr.model_id = users.id
              AND r.name = 'admin'
              AND r.guard_name = 'web'
        ) THEN 0 ELSE 1 END";
    }
}
