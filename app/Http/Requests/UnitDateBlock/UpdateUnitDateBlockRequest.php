<?php

namespace App\Http\Requests\UnitDateBlock;

use App\Models\UnitDateBlock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitDateBlockRequest extends FormRequest
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
                Rule::exists(UnitDateBlock::class, 'uuid'),
            ],
            'unit_uuid' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('units', 'uuid')->where(fn ($q) => $q->where('user_uuid', $this->user()->uuid)),
            ],
            'startDate' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'endDate' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'uuid' => $this->route('id') ?? $this->route('uuid'),
        ]);
    }
}
