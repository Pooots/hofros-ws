<?php

namespace App\Http\Requests\BookingPortal;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePortalActiveRequest extends FormRequest
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
            'isActive' => ['required', 'boolean'],
        ];
    }
}
