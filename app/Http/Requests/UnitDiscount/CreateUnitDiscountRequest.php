<?php

namespace App\Http\Requests\UnitDiscount;

use App\Models\UnitDiscount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUnitDiscountRequest extends FormRequest
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
            'unit_uuid' => [
                'required',
                'uuid',
                Rule::exists('units', 'uuid')->where(fn ($query) => $query->where('user_uuid', $this->user()->uuid)),
            ],
            'discount_type' => ['required', 'string', Rule::in(UnitDiscount::TYPES)],
            'discount_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'min_days_in_advance' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'min_nights' => ['nullable', 'integer', 'min:1', 'max:365'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'status' => ['sometimes', 'string', Rule::in(UnitDiscount::STATUSES)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toModelPayload(): array
    {
        $validated = $this->validated();

        return [
            'user_uuid' => $this->user()->uuid,
            'unit_uuid' => $validated['unit_uuid'],
            'discount_type' => $validated['discount_type'],
            'discount_percent' => $validated['discount_percent'],
            'min_days_in_advance' => $validated['min_days_in_advance'] ?? null,
            'min_nights' => $validated['min_nights'] ?? null,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_to' => $validated['valid_to'] ?? null,
            'status' => $validated['status'] ?? UnitDiscount::STATUS_ACTIVE,
        ];
    }
}
