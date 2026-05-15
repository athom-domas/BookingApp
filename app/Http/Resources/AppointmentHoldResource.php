<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentHoldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'startsAt'         => $this->starts_at->toIso8601String(),
            'endsAt'           => $this->ends_at->toIso8601String(),
            'expiresAt'        => $this->expires_at->toIso8601String(),
            'status'           => $this->status,
            'staffId'          => $this->staff_id,
            'serviceIds'       => $this->service_ids,
            'isExpired'        => $this->isExpired(),
            'minutesRemaining' => max(0, (int) now()->diffInMinutes($this->expires_at, false)),
        ];
    }
}
