<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'propertyName' => $this->property_name,
            'contactEmail' => $this->contact_email,
            'phone' => $this->phone,
            'address' => $this->address,
            'currency' => $this->currency,
            'checkInTime' => $this->check_in_time,
            'checkOutTime' => $this->check_out_time,
        ];
    }
}
