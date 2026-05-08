<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'     => ['required', 'date'],
            'staff_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
