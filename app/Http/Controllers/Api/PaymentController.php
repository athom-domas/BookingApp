<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function confirm(Appointment $appointment): JsonResponse
    {
        if ($appointment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $payment = $this->paymentService->confirmPayment($appointment->id);
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $payment]);
    }
}
