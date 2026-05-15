<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GetAvailableSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'              => 'required|date_format:Y-m-d|after_or_equal:today',
            'serviceIds'        => 'required|array|min:1',
            'serviceIds.*'      => 'integer|exists:services,id',
            'staffId'           => 'nullable|integer|exists:users,id',
            'staffPreference'   => 'nullable|in:specific,any',
        ];
    }

    public function getServiceIds(): array
    {
        $ids = $this->input('serviceIds');

        if (is_string($ids)) {
            return array_map('intval', explode(',', $ids));
        }

        return array_map('intval', (array) $ids);
    }
}
