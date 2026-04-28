<?php

namespace App\Http\Resources;

use App\Models\BookingPortalConnection;
use App\Models\User;
use App\Support\GuestPortalLayout;
use App\Support\GuestPortalUnits;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicDirectPortalResource extends JsonResource
{
    /**
     * @param  array{user: User, connection: BookingPortalConnection, slug: string}  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource['user'];
        /** @var BookingPortalConnection $row */
        $row = $this->resource['connection'];
        $slug = $this->resource['slug'];

        return [
            'slug' => $slug,
            'merchantName' => $user->merchant_name,
            'headline' => $row->guest_portal_headline,
            'message' => $row->guest_portal_message,
            'pageTitle' => $row->guest_portal_page_title,
            'themePreset' => $row->guest_portal_theme_preset ?? 'bold_modern',
            'primaryColor' => $row->guest_portal_primary_color ?? '#1B4F8A',
            'accentColor' => $row->guest_portal_accent_color ?? '#F5A623',
            'heroImageUrl' => $row->guest_portal_hero_image_url
                ?? 'https://images.unsplash.com/photo-1566073771259-6a850eaba8c9?auto=format&fit=crop&w=1600&q=80',
            'layout' => GuestPortalLayout::normalize($row->guest_portal_layout),
            'units' => GuestPortalUnits::publicPayloadForUserUuid($user->uuid),
        ];
    }
}
