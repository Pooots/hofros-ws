<?php

namespace App\Http\Requests\UnitDiscount;

use App\Models\UnitDiscount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitDiscountRequest extends FormRequest
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
                Rule::exists(UnitDiscount::class, 'uuid'),
            ],
            'unit_uuid' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('units', 'uuid')->where(fn ($query) => $query->where('user_uuid', $this->user()->uuid)),
            ],
            'discount_type' => ['sometimes', 'required', 'string', Rule::in(UnitDiscount::TYPES)],
            'discount_percent' => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:100'],
            'min_days_in_advance' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3650'],
            'min_nights' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_to' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'required', 'string', Rule::in(UnitDiscount::STATUSES)],
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
        $payload = [];

        $copy = ['unit_uuid', 'discount_type', 'discount_percent', 'min_days_in_advance', 'min_nights', 'valid_from', 'valid_to', 'status'];
        foreach ($copy as $key) {
            if (array_key_exists($key, $validated)) {
                $payload[$key] = $validated[$key];
            }
        }

        return $payload;
    }
}
