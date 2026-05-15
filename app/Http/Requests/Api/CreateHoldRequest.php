<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serviceIds'        => 'required|array|min:1',
            'serviceIds.*'      => 'integer|exists:services,id',
            'date'              => 'required|date_format:Y-m-d|after_or_equal:today',
            'slotStart'         => 'required|date_format:H:i',
            'slotEnd'           => 'required|date_format:H:i|after:slotStart',
            'staffId'           => 'nullable|integer|exists:users,id',
            'staffPreference'   => 'nullable|in:specific,any',
        ];
    }
}
