<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'start'              => $this['start'],
            'end'                => $this['end'],
            'availableOperators' => $this['availableOperators'] ?? [],
            'operatorCount'      => count($this['availableOperators'] ?? []),
        ];
    }
}
