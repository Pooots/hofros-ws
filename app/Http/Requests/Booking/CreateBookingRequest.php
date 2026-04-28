<?php

namespace App\Http\Requests\Booking;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBookingRequest extends FormRequest
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
            'unitId' => [
                'required',
                'uuid',
                Rule::exists('units', 'uuid')->where(fn ($q) => $q->where('user_uuid', $this->user()->uuid)),
            ],
            'guestName' => ['required', 'string', 'max:255'],
            'guestEmail' => ['required', 'string', 'email', 'max:255'],
            'guestPhone' => ['required', 'string', 'max:64'],
            'checkIn' => ['required', 'date_format:Y-m-d'],
            'checkOut' => ['required', 'date_format:Y-m-d', 'after:checkIn'],
            'adults' => ['required', 'integer', 'min:1', 'max:500'],
            'children' => ['required', 'integer', 'min:0', 'max:500'],
            'source' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'string', Rule::in(Booking::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'totalPrice' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
