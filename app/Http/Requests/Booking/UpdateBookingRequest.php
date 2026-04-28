<?php

namespace App\Http\Requests\Booking;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingRequest extends FormRequest
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
            'uuid' => [
                'required',
                'uuid',
                Rule::exists(Booking::class, 'uuid')->whereNull('deleted_at'),
            ],
            'unitId' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('units', 'uuid')->where(fn ($q) => $q->where('user_uuid', $this->user()->uuid)),
            ],
            'guestName' => ['sometimes', 'required', 'string', 'max:255'],
            'guestEmail' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'guestPhone' => ['sometimes', 'required', 'string', 'max:64'],
            'checkIn' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'checkOut' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'adults' => ['sometimes', 'required', 'integer', 'min:1', 'max:500'],
            'children' => ['sometimes', 'required', 'integer', 'min:0', 'max:500'],
            'source' => ['sometimes', 'nullable', 'string', 'max:32'],
            'status' => ['sometimes', 'required', 'string', Rule::in(Booking::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'totalPrice' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'uuid' => $this->route('id') ?? $this->route('uuid'),
        ]);
    }
}
