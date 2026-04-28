<?php

namespace App\Http\Resources;

use App\Http\Repositories\BookingPortalRepository;
use App\Models\BookingPortalConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class BookingPortalChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BookingPortalConnection $row */
        $row = $this->resource;
        $merchantSlug = $this->additional['merchantSlug'] ?? (Str::slug((string) ($row->user?->merchant_name ?? '')) ?: 'merchant');

        $catalog = (new BookingPortalRepository())->portalCatalog();
        $meta = $catalog[$row->portal_key] ?? ['name' => $row->portal_key, 'tint' => 'slate'];

        $isDirect = $row->portal_key === BookingPortalRepository::DIRECT_WEBSITE_KEY;
        $designDone = (bool) $row->guest_portal_design_completed;
        $live = (bool) $row->guest_portal_live;

        $isConnectedPayload = $isDirect ? $live : (bool) $row->is_connected;

        $disconnectedLabel = 'Connect';
        $disconnectedKind = 'connect';
        if ($isDirect) {
            if (! $designDone) {
                $disconnectedLabel = 'Build a direct website';
                $disconnectedKind = 'build_direct_website';
            } else {
                $disconnectedLabel = 'Open website builder';
                $disconnectedKind = 'open_website_builder';
            }
        }

        $visitUrl = ($isDirect && $live) ? '/'.$merchantSlug.'/directportal' : null;

        return [
            'id' => $row->portal_key,
            /** @deprecated Use id; kept for older clients that read portal key here */
            'uuid' => $row->portal_key,
            'name' => $meta['name'],
            'logoUrl' => null,
            'tint' => $meta['tint'],
            'isConnected' => $isConnectedPayload,
            'status' => self::statusLabel($row),
            'listingCount' => $row->listing_count,
            'lastSync' => $row->last_synced_at?->toIso8601String(),
            'isActive' => $row->is_active,
            'disconnectedActionLabel' => $disconnectedLabel,
            'disconnectedActionKind' => $disconnectedKind,
            'guestPortalLive' => $live,
            'guestPortalDesignCompleted' => $designDone,
            'visitUrl' => $visitUrl,
        ];
    }

    public static function statusLabel(BookingPortalConnection $row): string
    {
        if ($row->portal_key === BookingPortalRepository::DIRECT_WEBSITE_KEY) {
            if ($row->guest_portal_live) {
                return 'Live and connected';
            }
            if ($row->guest_portal_design_completed) {
                return 'Testing';
            }

            return 'Not connected';
        }

        if (! $row->is_connected) {
            return 'Not connected';
        }
        if ($row->has_sync_issue) {
            return 'Sync issue';
        }

        return 'Connected';
    }
}
