<?php

namespace App\Http\Resources;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitDiscountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'unit_uuid' => $this->unit_uuid,
            'unit_name' => $this->unit?->name ?? Unit::query()->whereKey($this->unit_uuid)->value('name'),
            'discount_type' => $this->discount_type,
            'discount_percent' => (float) $this->discount_percent,
            'min_days_in_advance' => $this->min_days_in_advance,
            'min_nights' => $this->min_nights,
            'valid_from' => $this->valid_from?->format('Y-m-d'),
            'valid_to' => $this->valid_to?->format('Y-m-d'),
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
