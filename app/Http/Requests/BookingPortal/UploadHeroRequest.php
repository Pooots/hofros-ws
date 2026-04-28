<?php

namespace App\Http\Requests\BookingPortal;

use Illuminate\Foundation\Http\FormRequest;

class UploadHeroRequest extends FormRequest
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
            'image' => ['required', 'file', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ];
    }
}
