<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'scheduledDate'  => $this->scheduled_date?->toIso8601String(),
            'status'         => $this->status,
            'staffId'        => $this->staff_id,
            'staffName'      => $this->whenLoaded('staff', fn () => $this->staff?->name),
            'service'        => $this->whenLoaded('service', fn () => [
                'id'       => $this->service->id,
                'name'     => $this->service->name,
                'duration' => $this->service->duration_minutes,
            ]),
            'finalPrice'     => $this->final_price,
            'notes'          => $this->notes,
            'canBeCancelled' => $this->canBeCancelled(),
        ];
    }
}
