<?php

namespace App\Http\Resources;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $defaults = Unit::defaultWeekSchedule();
        $week = array_merge(
            $defaults,
            is_array($this->week_schedule) ? array_intersect_key($this->week_schedule, $defaults) : []
        );

        return [
            'uuid' => $this->uuid,
            'propertyId' => $this->property_uuid,
            'propertyName' => $this->property?->property_name,
            'name' => $this->name,
            'details' => $this->details,
            'description' => $this->description,
            'images' => is_array($this->images) ? $this->images : [],
            'type' => $this->type,
            'maxGuests' => $this->max_guests,
            'bedrooms' => $this->bedrooms,
            'beds' => $this->beds,
            'pricePerNight' => (float) $this->price_per_night,
            'currency' => $this->currency,
            'status' => $this->status,
            'weekSchedule' => $week,
        ];
    }
}
