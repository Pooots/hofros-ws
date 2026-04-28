<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferenceResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'newBooking' => (bool) $this->new_booking,
            'cancellation' => (bool) $this->cancellation,
            'checkIn' => (bool) $this->check_in,
            'checkOut' => (bool) $this->check_out,
            'payment' => (bool) $this->payment,
            'review' => (bool) $this->review,
        ];
    }
}
