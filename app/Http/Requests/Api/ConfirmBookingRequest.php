<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'holdId'     => 'required|integer|exists:appointment_holds,id',
            'totalPrice' => 'nullable|numeric|min:0',
            'notes'      => 'nullable|string|max:500',
        ];
    }
}
