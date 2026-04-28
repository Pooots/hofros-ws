<?php

namespace App\Http\Requests\UnitDateBlock;

use Illuminate\Foundation\Http\FormRequest;

class ListUnitDateBlockRequest extends FormRequest
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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'unit_uuid' => ['nullable', 'uuid'],
        ];
    }
}
