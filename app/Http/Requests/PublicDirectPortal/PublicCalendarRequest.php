<?php

namespace App\Http\Requests\PublicDirectPortal;

use Illuminate\Foundation\Http\FormRequest;

class PublicCalendarRequest extends FormRequest
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
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after:from'],
            'unitId' => ['nullable', 'string'],
        ];
    }
}
