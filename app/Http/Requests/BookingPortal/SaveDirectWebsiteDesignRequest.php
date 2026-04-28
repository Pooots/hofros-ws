<?php

namespace App\Http\Requests\BookingPortal;

use Illuminate\Foundation\Http\FormRequest;

class SaveDirectWebsiteDesignRequest extends FormRequest
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
        return array_merge([
            'headline' => ['required', 'string', 'max:500'],
            'message' => ['required', 'string', 'max:4000'],
            'pageTitle' => ['sometimes', 'nullable', 'string', 'max:160'],
        ], SaveDirectWebsiteContentRequest::guestPortalVisualRules());
    }
}
