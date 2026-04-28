<?php

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUnitRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:200'],
            'sort' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(Unit::STATUSES)],
            'property_uuid' => ['nullable', 'uuid'],
        ];
    }
}
