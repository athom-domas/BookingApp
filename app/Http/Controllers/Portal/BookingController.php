<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreBookingRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
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
        $services = Service::active()
            ->with(['staff' => fn ($query) => $query
                ->whereHas('roles', fn ($roleQuery) => $roleQuery
                    ->where('name', 'staff')
                    ->where('guard_name', 'web'))
                ->orderBy('name')])
            ->orderBy('name')
            ->get();

        $staff = User::whereHas('roles', fn ($roleQuery) => $roleQuery
            ->where('name', 'staff')
            ->where('guard_name', 'web'))
            ->whereHas('services', fn ($query) => $query->active())
            ->with(['services' => fn ($query) => $query->active()->select('services.id', 'services.name')])
            ->orderBy('name')
            ->get();

        return view('welcome', [
            'services' => $services,
            'staff' => $staff,
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        try {
            $appointment = $this->appointmentService->bookAppointment(
                userId: $request->user()->id,
                serviceId: $request->integer('service_id'),
                staffId: $request->integer('staff_id'),
                scheduledDate: Carbon::parse($request->string('scheduled_date')),
            );

            if ($request->filled('notes')) {
                $appointment->update(['notes' => $request->string('notes')]);
            }

            $amountCents = (int) round((float) $appointment->final_price * 100);
            $this->paymentService->initiateStripePayment($appointment->id, $amountCents);
        } catch (BookingException $e) {
            return back()
                ->withInput()
                ->withErrors(['scheduled_date' => $e->getMessage()]);
        }

        return redirect()
            ->route('portal.appointments.payment', $appointment)
            ->with('status', 'Prenotazione creata. Completa il pagamento per confermarla.');
    }
}
