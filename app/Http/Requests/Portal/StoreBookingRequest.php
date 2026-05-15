<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'service_ids'    => ['required', 'array', 'min:1'],
            'service_ids.*'  => ['integer', 'exists:services,id'],
            'staff_id'       => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_date' => ['required', 'date', 'after:now'],
            'payment_method' => ['required', 'in:online,in_salon'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
