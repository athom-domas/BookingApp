<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_date' => ['sometimes', 'date', 'after:now'],
            'notes'          => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
