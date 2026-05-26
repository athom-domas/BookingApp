<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GetAvailableDatesRequest;
use App\Http\Requests\Api\GetAvailableSlotsRequest;
use App\Http\Resources\SlotResource;
use App\Services\Booking\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

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
            Log::error('getAvailableSlots failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Impossibile recuperare gli slot disponibili.'], 400);
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
            Log::error('getAvailableDates failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Impossibile recuperare le date disponibili.'], 400);
        }
    }
}
