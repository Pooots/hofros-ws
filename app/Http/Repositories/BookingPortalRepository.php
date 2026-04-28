<?php

namespace App\Http\Repositories;

use App\Models\BookingPortalConnection;
use Illuminate\Database\Eloquent\Collection;

class BookingPortalRepository
{
    public const DIRECT_WEBSITE_KEY = 'direct_website';

    /**
     * @return array<string, array{name: string, tint: string}>
     */
    public function portalCatalog(): array
    {
        return [
            'direct_website' => ['name' => 'Direct Website', 'tint' => 'direct'],
            'booking_com' => ['name' => 'Booking.com', 'tint' => 'blue'],
            'expedia' => ['name' => 'Expedia', 'tint' => 'yellow'],
            'vrbo' => ['name' => 'VRBO', 'tint' => 'slate'],
            'tripadvisor' => ['name' => 'TripAdvisor', 'tint' => 'slate'],
            'airbnb' => ['name' => 'Airbnb', 'tint' => 'rose'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function guestPortalDefaults(): array
    {
        return [
            'guest_portal_live' => false,
            'guest_portal_design_completed' => false,
            'guest_portal_headline' => null,
            'guest_portal_message' => null,
            'guest_portal_theme_preset' => null,
            'guest_portal_primary_color' => null,
            'guest_portal_accent_color' => null,
            'guest_portal_hero_image_url' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultAttributesForKey(string $key): array
    {
        return array_merge([
            'is_connected' => false,
            'is_active' => false,
            'listing_count' => 0,
            'last_synced_at' => null,
            'has_sync_issue' => false,
        ], $this->guestPortalDefaults());
    }

    public function ensureRowsForUser(string $userUuid): void
    {
        foreach (array_keys($this->portalCatalog()) as $portalKey) {
            BookingPortalConnection::firstOrCreate(
                [
                    'user_uuid' => $userUuid,
                    'portal_key' => $portalKey,
                ],
                $this->defaultAttributesForKey($portalKey)
            );
        }
    }

    public function findOrFail(string $userUuid, string $portalKey): BookingPortalConnection
    {
        return BookingPortalConnection::query()
            ->where('user_uuid', $userUuid)
            ->where('portal_key', $portalKey)
            ->firstOrFail();
    }

    public function directWebsiteRow(string $userUuid): BookingPortalConnection
    {
        $this->ensureRowsForUser($userUuid);

        return $this->findOrFail($userUuid, self::DIRECT_WEBSITE_KEY);
    }

    /**
     * @return Collection<int, BookingPortalConnection>
     */
    public function getAllForUser(string $userUuid): Collection
    {
        $this->ensureRowsForUser($userUuid);

        return BookingPortalConnection::query()
            ->where('user_uuid', $userUuid)
            ->with(['user:uuid,merchant_name'])
            ->get();
    }

    /**
     * @return Collection<int, BookingPortalConnection>
     */
    public function getDisconnectedForUser(string $userUuid): Collection
    {
        $this->ensureRowsForUser($userUuid);

        return BookingPortalConnection::query()
            ->where('user_uuid', $userUuid)
            ->where('is_connected', false)
            ->where('portal_key', '!=', self::DIRECT_WEBSITE_KEY)
            ->get();
    }
}
