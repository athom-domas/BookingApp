<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BookAppointmentRequest;
use App\Http\Requests\Api\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(): JsonResponse
    {
        $appointments = Appointment::where('user_id', auth()->id())
            ->with(['service', 'staff', 'payment'])
            ->latest('scheduled_date')
            ->get();

        return response()->json(['data' => $appointments]);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        if ($appointment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(['data' => $appointment->load('service', 'staff', 'payment')]);
    }

    public function store(BookAppointmentRequest $request): JsonResponse
    {
        try {
            $appointment = $this->appointmentService->bookAppointment(
                userId:        $request->user()->id,
                serviceId:     $request->integer('service_id'),
                staffId:       $request->integer('staff_id'),
                scheduledDate: Carbon::parse($request->string('scheduled_date')),
            );
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($request->filled('notes')) {
            $appointment->update(['notes' => $request->string('notes')]);
        }

        $amountCents = (int) round($appointment->final_price * 100);
        $payment = $this->paymentService->initiateStripePayment($appointment->id, $amountCents);

        return response()->json([
            'data' => [
                'appointment'       => $appointment->load('service', 'staff'),
                'payment_intent_id' => $payment->stripe_transaction_id,
            ],
        ], 201);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($appointment->status !== 'pending') {
            return response()->json(['message' => 'Solo appuntamenti pending possono essere modificati.'], 422);
        }

        if ($request->has('scheduled_date')) {
            $newDate = Carbon::parse($request->string('scheduled_date'));

            if (! $this->appointmentService->validateAvailability($appointment->staff_id, $appointment->service_id, $newDate)) {
                return response()->json(['message' => 'Staff non disponibile per questa data e ora.'], 422);
            }

            $appointment->update(['scheduled_date' => $newDate]);
        }

        if ($request->has('notes')) {
            $appointment->update(['notes' => $request->string('notes')]);
        }

        return response()->json(['data' => $appointment->fresh()->load('service', 'staff')]);
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        if ($appointment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $this->appointmentService->cancelAppointment($appointment->id, null);
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Appuntamento cancellato con successo.']);
    }
}
