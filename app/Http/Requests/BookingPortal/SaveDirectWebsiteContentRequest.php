<?php

namespace App\Http\Requests\BookingPortal;

use App\Support\GuestPortalLayout;
use Illuminate\Foundation\Http\FormRequest;

class SaveDirectWebsiteContentRequest extends FormRequest
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
            'headline' => ['sometimes', 'nullable', 'string', 'max:500'],
            'message' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'pageTitle' => ['sometimes', 'nullable', 'string', 'max:160'],
        ], self::guestPortalVisualRules());
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public static function guestPortalVisualRules(): array
    {
        return [
            'themePreset' => ['sometimes', 'nullable', 'string', 'max:48'],
            'primaryColor' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accentColor' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'heroImageUrl' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'layout' => ['sometimes', 'nullable', 'array'],
            'layout.businessName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'layout.businessTagline' => ['sometimes', 'nullable', 'string', 'max:500'],
            'layout.phone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'layout.email' => ['sometimes', 'nullable', 'string', 'max:255'],
            'layout.address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'layout.amenities' => ['sometimes', 'array', 'max:40'],
            'layout.amenities.*' => ['string', 'max:64'],
            'layout.reviews' => ['sometimes', 'array', 'max:20'],
            'layout.reviews.*.name' => ['required', 'string', 'max:64'],
            'layout.reviews.*.initial' => ['nullable', 'string', 'max:4'],
            'layout.reviews.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'layout.reviews.*.text' => ['required', 'string', 'max:2000'],
            'layout.sectionOrder' => ['sometimes', 'array', 'max:10'],
            'layout.sectionOrder.*' => ['string', 'in:'.implode(',', GuestPortalLayout::SECTION_IDS)],
            'layout.sectionVisibility' => ['sometimes', 'array'],
            'layout.sectionVisibility.hero' => ['sometimes', 'boolean'],
            'layout.sectionVisibility.units' => ['sometimes', 'boolean'],
            'layout.sectionVisibility.amenities' => ['sometimes', 'boolean'],
            'layout.sectionVisibility.reviews' => ['sometimes', 'boolean'],
            'layout.sectionVisibility.contact' => ['sometimes', 'boolean'],
            'layout.showReviews' => ['sometimes', 'boolean'],
            'layout.showMap' => ['sometimes', 'boolean'],
        ];
    }
}
