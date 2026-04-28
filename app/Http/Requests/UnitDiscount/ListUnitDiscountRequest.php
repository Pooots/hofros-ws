<?php

namespace App\Http\Requests\UnitDiscount;

use App\Models\UnitDiscount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUnitDiscountRequest extends FormRequest
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
            'sort' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'unit_uuid' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(UnitDiscount::STATUSES)],
            'discount_type' => ['nullable', Rule::in(UnitDiscount::TYPES)],
        ];
    }
}
