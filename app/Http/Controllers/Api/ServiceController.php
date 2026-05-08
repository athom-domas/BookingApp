<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SlotsRequest;
use App\Models\Service;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    public function index(): JsonResponse
    {
        $services = Service::active()->get();

        return response()->json(['data' => $services]);
    }

    public function slots(SlotsRequest $request, Service $service): JsonResponse
    {
        $slots = $this->appointmentService->getAvailableSlots(
            serviceId: $service->id,
            staffId:   $request->integer('staff_id'),
            date:      $request->string('date'),
        );

        return response()->json(['data' => $slots]);
    }
}
