<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConfirmBookingRequest;
use App\Http\Requests\Api\CreateHoldRequest;
use App\Http\Requests\Api\GetAvailableDatesRequest;
use App\Http\Requests\Api\GetAvailableSlotsRequest;
use App\Http\Resources\AppointmentHoldResource;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\SlotResource;
use App\Models\AppointmentHold;
use App\Services\Booking\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
    ) {}

    /**
     * GET /api/booking/slots
     *
     * Returns dynamically calculated available slots.
     * Public endpoint — no auth required.
     */
    public function getAvailableSlots(GetAvailableSlotsRequest $request): JsonResponse
    {
        try {
            $slots = $this->appointmentService->getAvailableSlots([
                'date'            => $request->input('date'),
                'serviceIds'      => $request->getServiceIds(),
                'staffId'         => $request->input('staffId'),
                'staffPreference' => $request->input('staffPreference', 'any'),
            ]);

            return response()->json([
                'success' => true,
                'data'    => SlotResource::collection($slots),
                'count'   => count($slots),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/booking/available-dates
     *
     * Returns dates in a month that have at least one available slot.
     * Public endpoint — no auth required.
     */
    public function getAvailableDates(GetAvailableDatesRequest $request): JsonResponse
    {
        try {
            $dates = $this->appointmentService->getAvailableDates([
                'month'      => $request->input('month'),
                'serviceIds' => $request->getServiceIds(),
                'staffId'    => $request->input('staffId'),
            ]);

            return response()->json([
                'success' => true,
                'data'    => $dates,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/booking/hold
     *
     * Validates availability and creates a temporary hold.
     */
    public function createHold(CreateHoldRequest $request): JsonResponse
    {
        try {
            $date  = $request->input('date');
            $hold  = $this->appointmentService->createHold([
                'serviceIds'      => $request->input('serviceIds'),
                'date'            => $date,
                'slotStart'       => Carbon::createFromFormat('Y-m-d H:i', "$date {$request->input('slotStart')}"),
                'slotEnd'         => Carbon::createFromFormat('Y-m-d H:i', "$date {$request->input('slotEnd')}"),
                'staffId'         => $request->input('staffId'),
                'staffPreference' => $request->input('staffPreference', 'specific'),
                'sessionId'       => session()->getId(),
            ]);

            return response()->json([
                'success' => true,
                'data'    => new AppointmentHoldResource($hold),
                'message' => 'Hold created. Confirm within ' . $hold->expires_at->diffInMinutes(now()) . ' minutes.',
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/booking/holds/{hold}
     *
     * Returns current hold status with time-to-expiry.
     */
    public function getHold(AppointmentHold $hold): JsonResponse
    {
        return response()->json([
            'success'          => true,
            'data'             => new AppointmentHoldResource($hold),
            'isExpired'        => $hold->isExpired(),
            'minutesRemaining' => max(0, (int) now()->diffInMinutes($hold->expires_at, false)),
        ]);
    }

    /**
     * PUT /api/booking/holds/{hold}/extend
     *
     * Extends the hold TTL (called while user fills the booking form).
     */
    public function extendHold(AppointmentHold $hold): JsonResponse
    {
        try {
            $hold = $this->appointmentService->extendHold($hold);

            return response()->json([
                'success'          => true,
                'data'             => new AppointmentHoldResource($hold),
                'minutesRemaining' => max(0, (int) now()->diffInMinutes($hold->expires_at, false)),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/booking/confirm
     *
     * Converts an active hold into a confirmed appointment.
     */
    public function confirmBooking(ConfirmBookingRequest $request): JsonResponse
    {
        $hold = AppointmentHold::find($request->integer('holdId'));

        if (! $hold) {
            return response()->json(['success' => false, 'error' => 'Hold not found'], 404);
        }

        try {
            $appointment = $this->appointmentService->confirmFromHold($hold, [
                'final_price' => $request->input('totalPrice'),
                'notes'       => $request->input('notes'),
            ]);

            return response()->json([
                'success' => true,
                'data'    => new AppointmentResource($appointment->load(['service', 'staff'])),
                'message' => 'Appointment confirmed!',
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
