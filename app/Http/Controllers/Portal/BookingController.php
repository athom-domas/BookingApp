<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreBookingRequest;
use App\Models\SalonProfile;
use App\Models\SalonReview;
use App\Models\Service;
use App\Models\User;
use App\Services\Booking\AppointmentService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(): View
    {
        $profile  = SalonProfile::current()->load('media');
        $services = Service::active()->orderBy('name')->get();
        $staff    = User::whereHas('roles', fn ($q) => $q->where('name', 'staff')->where('guard_name', 'web'))
            ->with('media')
            ->where(fn ($q) => $q
                ->whereNotNull('bio')
                ->orWhereHas('media', fn ($m) => $m->where('collection_name', 'avatar'))
            )
            ->get();
        $reviews = SalonReview::published()->ordered()->get();

        return view('welcome', compact('profile', 'services', 'staff', 'reviews'));
    }

    public function create(): View
    {
        $services = Service::active()
            ->with(['staff' => fn ($q) => $q
                ->whereHas('roles', fn ($r) => $r->where('name', 'staff')->where('guard_name', 'web'))
                ->orderBy('name')])
            ->orderBy('name')
            ->get();

        $staff = User::whereHas('roles', fn ($q) => $q->where('name', 'staff')->where('guard_name', 'web'))
            ->whereHas('services', fn ($q) => $q->active())
            ->with(['services' => fn ($q) => $q->active()->select('services.id', 'services.name')])
            ->orderBy('name')
            ->get();

        return view('portal.booking.index', [
            'services' => $services,
            'staff'    => $staff,
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
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
}
