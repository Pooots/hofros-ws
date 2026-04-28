<?php

namespace App\Http\Requests\Property;

use Illuminate\Foundation\Http\FormRequest;

class CreatePropertyRequest extends FormRequest
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
            'propertyName' => ['required', 'string', 'max:255'],
            'contactEmail' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:2000'],
            'currency' => ['required', 'string', 'max:16'],
            'checkInTime' => ['required', 'string', 'max:8'],
            'checkOutTime' => ['required', 'string', 'max:8'],
        ];
    }

    /**
     * Map camelCase request payload onto snake_case columns the repository expects.
     *
     * @return array<string, mixed>
     */
    public function toModelPayload(): array
    {
        $validated = $this->validated();

        return [
            'user_uuid' => $this->user()?->uuid,
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
