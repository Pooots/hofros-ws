<?php

namespace App\Http\Requests\Property;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequest extends FormRequest
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
                Rule::exists(Property::class, 'uuid'),
            ],
            'propertyName' => ['required', 'string', 'max:255'],
            'contactEmail' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:2000'],
            'currency' => ['required', 'string', 'max:16'],
            'checkInTime' => ['required', 'string', 'max:8'],
            'checkOutTime' => ['required', 'string', 'max:8'],
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
    public function toModelPayload(): array
    {
        $validated = $this->validated();

        return [
            'property_name' => $validated['propertyName'],
            'contact_email' => $validated['contactEmail'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'currency' => $validated['currency'],
            'check_in_time' => $validated['checkInTime'],
            'check_out_time' => $validated['checkOutTime'],
        ];
    }
}
