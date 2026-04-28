<?php

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
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
                Rule::exists(Unit::class, 'uuid'),
            ],
            'propertyId' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('properties', 'uuid')->where(fn ($query) => $query->where('user_uuid', $this->user()->uuid)),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'details' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'images' => ['sometimes', 'nullable', 'array', 'max:20'],
            'images.*' => ['string', 'max:2048'],
            'type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'maxGuests' => ['sometimes', 'required', 'integer', 'min:1', 'max:500'],
            'bedrooms' => ['sometimes', 'required', 'integer', 'min:0', 'max:200'],
            'beds' => ['sometimes', 'required', 'integer', 'min:0', 'max:500'],
            'pricePerNight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:16'],
            'status' => ['sometimes', 'required', 'string', Rule::in(Unit::STATUSES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'uuid' => $this->route('id') ?? $this->route('uuid'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toModelPayload(?string $fallbackCurrency = null): array
    {
        $validated = $this->validated();
        $payload = [];

        $map = [
            'propertyId' => 'property_uuid',
            'name' => 'name',
            'details' => 'details',
            'description' => 'description',
            'images' => 'images',
            'type' => 'type',
            'maxGuests' => 'max_guests',
            'bedrooms' => 'bedrooms',
            'beds' => 'beds',
        ];

        foreach ($map as $from => $to) {
            if (array_key_exists($from, $validated)) {
                $payload[$to] = $validated[$from];
            }
        }

        if (array_key_exists('pricePerNight', $validated)) {
            $payload['price_per_night'] = $validated['pricePerNight'] ?? 0;
        }
        if (array_key_exists('currency', $validated)) {
            $payload['currency'] = $validated['currency'] ?? $fallbackCurrency;
        }
        if (array_key_exists('status', $validated)) {
            $payload['status'] = $validated['status'];
        }

        return $payload;
    }
}
