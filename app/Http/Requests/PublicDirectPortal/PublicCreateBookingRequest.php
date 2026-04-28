<?php

namespace App\Http\Requests\PublicDirectPortal;

use App\Support\BookingStayConflict;
use Illuminate\Foundation\Http\FormRequest;

class PublicCreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unitId' => ['required', 'string'],
            'guestName' => ['required', 'string', 'max:255'],
            'guestEmail' => ['required', 'string', 'email', 'max:255'],
            'guestPhone' => ['required', 'string', 'max:64'],
            'checkIn' => ['required', 'date_format:Y-m-d'],
            'checkOut' => ['required', 'date_format:Y-m-d', 'after:checkIn'],
            'adults' => ['required', 'integer', 'min:1', 'max:500'],
            'children' => ['required', 'integer', 'min:0', 'max:500'],
            'unitCount' => ['sometimes', 'integer', 'min:1', 'max:'.BookingStayConflict::MAX_PORTAL_UNITS_PER_BOOKING],
            'promoCode' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
