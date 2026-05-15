<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GetAvailableDatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serviceIds'   => 'required|array|min:1',
            'serviceIds.*' => 'integer|exists:services,id',
            'staffId'      => 'nullable|integer|exists:users,id',
            'month'        => ['required', 'date_format:Y-m', 'before_or_equal:' . now()->addWeeks(52)->format('Y-m')],
        ];
    }

    public function getServiceIds(): array
    {
        return array_map('intval', (array) $this->input('serviceIds'));
    }
}
