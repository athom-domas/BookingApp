<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class BookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id'     => ['required', 'integer', 'exists:services,id'],
            'staff_id'       => ['required', 'integer', 'exists:users,id'],
            'scheduled_date' => ['required', 'date', 'after:now'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
