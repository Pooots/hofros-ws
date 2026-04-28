<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoCodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'discountType' => $this->discount_type,
            'discountValue' => (float) $this->discount_value,
            'minNights' => $this->min_nights,
            'maxUses' => $this->max_uses,
            'usesCount' => $this->uses_count,
            'status' => $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
