<?php

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUnitRequest extends FormRequest
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
            'propertyId' => [
                'required',
                'uuid',
                Rule::exists('properties', 'uuid')->where(fn ($query) => $query->where('user_uuid', $this->user()->uuid)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['string', 'max:2048'],
            'type' => ['nullable', 'string', 'max:64'],
            'maxGuests' => ['required', 'integer', 'min:1', 'max:500'],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:200'],
            'beds' => ['required', 'integer', 'min:0', 'max:500'],
            'pricePerNight' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:16'],
            'status' => ['required', 'string', Rule::in(Unit::STATUSES)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toModelPayload(string $fallbackCurrency): array
    {
        $validated = $this->validated();

        return [
            'user_uuid' => $this->user()->uuid,
            'property_uuid' => $validated['propertyId'],
            'name' => $validated['name'],
            'details' => $validated['details'] ?? null,
            'description' => $validated['description'] ?? null,
            'images' => $validated['images'] ?? [],
            'type' => $validated['type'] ?? null,
            'max_guests' => $validated['maxGuests'],
            'bedrooms' => $validated['bedrooms'],
            'beds' => $validated['beds'],
            'price_per_night' => $validated['pricePerNight'] ?? 0,
            'currency' => $validated['currency'] ?? $fallbackCurrency,
            'status' => $validated['status'],
            'week_schedule' => Unit::defaultWeekSchedule(),
        ];
    }
}
