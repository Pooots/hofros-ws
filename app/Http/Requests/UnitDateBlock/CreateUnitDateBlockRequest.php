<?php

namespace App\Http\Requests\UnitDateBlock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUnitDateBlockRequest extends FormRequest
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
                Rule::exists('units', 'uuid')->where(fn ($q) => $q->where('user_uuid', $this->user()->uuid)),
            ],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after:startDate'],
            'label' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
