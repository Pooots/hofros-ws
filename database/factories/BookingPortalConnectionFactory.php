<?php

namespace Database\Factories;

use App\Models\BookingPortalConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPortalConnection>
 */
class BookingPortalConnectionFactory extends Factory
{
    protected $model = BookingPortalConnection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'portal_key' => 'direct_website',
            'is_connected' => true,
            'is_active' => true,
            'listing_count' => 0,
            'last_synced_at' => null,
            'has_sync_issue' => false,
            'guest_portal_live' => false,
            'guest_portal_design_completed' => false,
            'guest_portal_headline' => null,
            'guest_portal_message' => null,
            'guest_portal_page_title' => null,
            'guest_portal_theme_preset' => null,
            'guest_portal_primary_color' => null,
            'guest_portal_accent_color' => null,
            'guest_portal_hero_image_url' => null,
            'guest_portal_layout' => null,
        ];
    }
}
