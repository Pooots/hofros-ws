<?php

namespace App\Http\Requests\PublicDirectPortal;

use App\Support\BookingStayConflict;
use Illuminate\Foundation\Http\FormRequest;

class PublicQuoteRequest extends FormRequest
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
            'checkIn' => ['required', 'date_format:Y-m-d'],
            'checkOut' => ['required', 'date_format:Y-m-d', 'after:checkIn'],
            'unitCount' => ['sometimes', 'integer', 'min:1', 'max:'.BookingStayConflict::MAX_PORTAL_UNITS_PER_BOOKING],
            'promoCode' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
