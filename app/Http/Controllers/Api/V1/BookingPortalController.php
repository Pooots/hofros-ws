<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BookingPortalConnection;
use App\Support\GuestPortalLayout;
use App\Support\GuestPortalUnits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BookingPortalController extends Controller
{
    /**
     * @return array<string, array{name: string, tint: string}>
     */
    private function portalCatalog(): array
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
    private function guestPortalDefaults(): array
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
    private function defaultAttributesForKey(string $key): array
    {
        $guest = $this->guestPortalDefaults();

        return match ($key) {
            'airbnb', 'booking_com', 'expedia', 'vrbo', 'tripadvisor', 'direct_website' => array_merge([
                'is_connected' => false,
                'is_active' => false,
                'listing_count' => 0,
                'last_synced_at' => null,
                'has_sync_issue' => false,
            ], $guest),
            default => array_merge([
                'is_connected' => false,
                'is_active' => false,
                'listing_count' => 0,
                'last_synced_at' => null,
                'has_sync_issue' => false,
            ], $guest),
        };
    }

    private function ensureRowsForUser(Request $request): void
    {
        $userId = $request->user()->id;

        foreach (array_keys($this->portalCatalog()) as $portalKey) {
            BookingPortalConnection::firstOrCreate(
                [
                    'user_id' => $userId,
                    'portal_key' => $portalKey,
                ],
                $this->defaultAttributesForKey($portalKey)
            );
        }
    }

    private function directWebsiteRow(Request $request): BookingPortalConnection
    {
        $this->ensureRowsForUser($request);

        return BookingPortalConnection::query()
            ->where('user_id', $request->user()->id)
            ->where('portal_key', 'direct_website')
            ->firstOrFail();
    }

    private function statusLabel(BookingPortalConnection $row): string
    {
        if ($row->portal_key === 'direct_website') {
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

    /**
     * @return array<string, mixed>
     */
    private function channelPayload(BookingPortalConnection $row, ?string $merchantSlug = null): array
    {
        $catalog = $this->portalCatalog();
        $meta = $catalog[$row->portal_key] ?? ['name' => $row->portal_key, 'tint' => 'slate'];

        $isDirect = $row->portal_key === 'direct_website';
        $designDone = (bool) $row->guest_portal_design_completed;
        $live = (bool) $row->guest_portal_live;

        $isConnectedPayload = $isDirect
            ? $live
            : (bool) $row->is_connected;

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
        if ($merchantSlug === null) {
            $merchantSlug = Str::slug((string) ($row->user?->merchant_name ?? '')) ?: 'merchant';
        }
        $visitUrl = ($isDirect && $live) ? '/'.$merchantSlug.'/directportal' : null;

        return [
            'id' => $row->portal_key,
            'name' => $meta['name'],
            'logoUrl' => null,
            'tint' => $meta['tint'],
            'isConnected' => $isConnectedPayload,
            'status' => $this->statusLabel($row),
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

    public function index(Request $request): JsonResponse
    {
        $this->ensureRowsForUser($request);

        $order = array_keys($this->portalCatalog());

        $merchantSlug = Str::slug((string) ($request->user()->merchant_name ?? '')) ?: 'merchant';
        $rows = BookingPortalConnection::query()
            ->where('user_id', $request->user()->id)
            ->with(['user:id,merchant_name'])
            ->get()
            ->sortBy(fn (BookingPortalConnection $row) => array_search($row->portal_key, $order, true))
            ->values();

        $channels = $rows->map(fn (BookingPortalConnection $row) => $this->channelPayload($row, $merchantSlug))->values();

        $connected = $rows->filter(function (BookingPortalConnection $r): bool {
            if ($r->portal_key === 'direct_website') {
                return (bool) $r->guest_portal_live;
            }

            return (bool) $r->is_connected;
        });
        $connectedChannels = $connected->count();
        $totalListings = $connected->sum('listing_count');
        $syncIssues = $connected->filter(fn (BookingPortalConnection $r) => $r->has_sync_issue)->count();

        return response()->json([
            'channels' => $channels,
            'summary' => [
                'connectedChannels' => $connectedChannels,
                'totalListings' => $totalListings,
                'syncIssues' => $syncIssues,
            ],
        ]);
    }

    public function directWebsiteSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        $row = $this->directWebsiteRow($request);

        $slug = Str::slug((string) ($user->merchant_name ?? '')) ?: 'merchant';

        return response()->json([
            'merchantSlug' => $slug,
            'merchantName' => $user->merchant_name,
            'headline' => $row->guest_portal_headline,
            'message' => $row->guest_portal_message,
            'pageTitle' => $row->guest_portal_page_title,
            'guestPortalLive' => (bool) $row->guest_portal_live,
            'guestPortalDesignCompleted' => (bool) $row->guest_portal_design_completed,
            'themePreset' => $row->guest_portal_theme_preset ?? 'bold_modern',
            'primaryColor' => $row->guest_portal_primary_color ?? '#1B4F8A',
            'accentColor' => $row->guest_portal_accent_color ?? '#F5A623',
            'heroImageUrl' => $row->guest_portal_hero_image_url ?? $this->defaultGuestPortalHeroUrl(),
            'layout' => GuestPortalLayout::normalize($row->guest_portal_layout),
            'units' => GuestPortalUnits::publicPayloadForUserId($user->id),
        ]);
    }

    private function defaultGuestPortalHeroUrl(): string
    {
        return 'https://images.unsplash.com/photo-1566073771259-6a850eaba8c9?auto=format&fit=crop&w=1600&q=80';
    }

    public function uploadDirectWebsiteHero(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ]);

        $row = $this->directWebsiteRow($request);
        $file = $validated['image'];

        $dir = 'guest-portal-heroes/'.$request->user()->id;
        $ext = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg'));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'jpg';
        }
        $name = Str::uuid()->toString().'.'.$ext;
        $storedPath = $file->storeAs($dir, $name, 'public');

        $suffix = '/storage/'.str_replace('\\', '/', $storedPath);
        $url = rtrim($request->getSchemeAndHttpHost(), '/').$suffix;

        $row->update(['guest_portal_hero_image_url' => $url]);

        return response()->json([
            'message' => 'Hero photo uploaded.',
            'heroImageUrl' => $url,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyGuestPortalVisualFromRequest(BookingPortalConnection $row, array $validated): void
    {
        $updates = [];

        if (array_key_exists('themePreset', $validated) && $validated['themePreset'] !== null) {
            $updates['guest_portal_theme_preset'] = $validated['themePreset'];
        }
        if (array_key_exists('primaryColor', $validated) && $validated['primaryColor'] !== null) {
            $updates['guest_portal_primary_color'] = $validated['primaryColor'];
        }
        if (array_key_exists('accentColor', $validated) && $validated['accentColor'] !== null) {
            $updates['guest_portal_accent_color'] = $validated['accentColor'];
        }
        if (array_key_exists('heroImageUrl', $validated) && $validated['heroImageUrl'] !== null) {
            $updates['guest_portal_hero_image_url'] = $validated['heroImageUrl'];
        }

        if ($updates !== []) {
            $row->update($updates);
        }
    }

    private function guestPortalVisualRules(): array
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

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyGuestPortalLayoutFromValidated(BookingPortalConnection $row, array $validated): void
    {
        if (! array_key_exists('layout', $validated) || ! is_array($validated['layout'])) {
            return;
        }

        $row->refresh();
        $merged = GuestPortalLayout::normalize($row->guest_portal_layout, $validated['layout']);
        $row->update(['guest_portal_layout' => $merged]);
    }

    public function saveDirectWebsiteContent(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'headline' => ['sometimes', 'nullable', 'string', 'max:500'],
            'message' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'pageTitle' => ['sometimes', 'nullable', 'string', 'max:160'],
        ], $this->guestPortalVisualRules()));

        $row = $this->directWebsiteRow($request);

        $textUpdates = [];
        if (array_key_exists('headline', $validated) && $validated['headline'] !== null) {
            $textUpdates['guest_portal_headline'] = $validated['headline'];
        }
        if (array_key_exists('message', $validated) && $validated['message'] !== null) {
            $textUpdates['guest_portal_message'] = $validated['message'];
        }
        if (array_key_exists('pageTitle', $validated)) {
            $t = $validated['pageTitle'];
            $textUpdates['guest_portal_page_title'] = is_string($t) && trim($t) !== '' ? trim($t) : null;
        }

        if ($textUpdates !== []) {
            $row->update($textUpdates);
        }

        $this->applyGuestPortalVisualFromRequest($row, $validated);
        $this->applyGuestPortalLayoutFromValidated($row, $validated);

        return response()->json([
            'message' => 'Draft saved.',
            'channel' => $this->channelPayload($row->fresh()),
        ]);
    }

    public function saveDirectWebsiteDesign(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'headline' => ['required', 'string', 'max:500'],
            'message' => ['required', 'string', 'max:4000'],
            'pageTitle' => ['sometimes', 'nullable', 'string', 'max:160'],
        ], $this->guestPortalVisualRules()));

        $row = $this->directWebsiteRow($request);

        $designUpdates = [
            'guest_portal_headline' => $validated['headline'],
            'guest_portal_message' => $validated['message'],
            'guest_portal_design_completed' => true,
        ];
        if (array_key_exists('pageTitle', $validated)) {
            $t = $validated['pageTitle'];
            $designUpdates['guest_portal_page_title'] = is_string($t) && trim($t) !== '' ? trim($t) : null;
        }

        $row->update($designUpdates);

        $row->refresh();
        $this->applyGuestPortalVisualFromRequest($row, $validated);
        $this->applyGuestPortalLayoutFromValidated($row, $validated);

        return response()->json([
            'message' => 'Micro-site design saved. You can go live from the website builder when you are ready.',
            'channel' => $this->channelPayload($row->fresh()),
        ]);
    }

    public function setDirectWebsiteLive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'live' => ['required', 'boolean'],
        ]);

        $row = $this->directWebsiteRow($request);

        if ($validated['live'] && ! $row->guest_portal_design_completed) {
            return response()->json([
                'message' => 'Complete your micro-site design before publishing the guest link.',
            ], 422);
        }

        if ($validated['live'] && (! is_string($row->guest_portal_headline) || trim($row->guest_portal_headline) === '')) {
            return response()->json([
                'message' => 'Add a headline in the micro-site designer before going live.',
            ], 422);
        }

        $live = $validated['live'];

        $row->update([
            'guest_portal_live' => $live,
            'is_connected' => $live,
            'is_active' => $live,
            'listing_count' => $live ? max(1, (int) $row->listing_count) : 0,
            'last_synced_at' => $live ? Carbon::now() : null,
            'has_sync_issue' => false,
        ]);

        return response()->json([
            'message' => $live ? 'Direct portal is now live for guests.' : 'Direct portal is in testing mode. Guests no longer see the public page.',
            'channel' => $this->channelPayload($row->fresh()),
        ]);
    }

    public function updateActive(Request $request, string $portalKey): JsonResponse
    {
        $this->assertPortalKey($portalKey);

        $validated = $request->validate([
            'isActive' => ['required', 'boolean'],
        ]);

        $row = BookingPortalConnection::query()
            ->where('user_id', $request->user()->id)
            ->where('portal_key', $portalKey)
            ->firstOrFail();

        if (! $row->is_connected) {
            return response()->json([
                'message' => 'Connect this channel before enabling it.',
            ], 422);
        }

        $row->update(['is_active' => $validated['isActive']]);

        return response()->json($this->channelPayload($row->fresh()));
    }

    public function sync(Request $request, string $portalKey): JsonResponse
    {
        $this->assertPortalKey($portalKey);

        $row = BookingPortalConnection::query()
            ->where('user_id', $request->user()->id)
            ->where('portal_key', $portalKey)
            ->firstOrFail();

        if (! $row->is_connected) {
            return response()->json([
                'message' => 'Connect this channel before syncing.',
            ], 422);
        }

        $row->update([
            'last_synced_at' => Carbon::now(),
            'has_sync_issue' => false,
        ]);

        return response()->json([
            'message' => 'Sync queued for '.$portalKey.'.',
            'channel' => $this->channelPayload($row->fresh()),
        ]);
    }

    public function connect(Request $request, string $portalKey): JsonResponse
    {
        $this->assertPortalKey($portalKey);

        if ($portalKey === 'direct_website') {
            return response()->json([
                'message' => 'Use “Build a direct website” in the app to open the website builder and copy your guest link.',
            ], 422);
        }

        $row = BookingPortalConnection::query()
            ->where('user_id', $request->user()->id)
            ->where('portal_key', $portalKey)
            ->firstOrFail();

        if ($row->is_connected) {
            return response()->json([
                'message' => 'This channel is already connected.',
                'channel' => $this->channelPayload($row),
            ]);
        }

        $row->update([
            'is_connected' => true,
            'is_active' => true,
            'listing_count' => max(1, $row->listing_count),
            'last_synced_at' => Carbon::now(),
            'has_sync_issue' => false,
        ]);

        return response()->json([
            'message' => 'Channel connected. Complete OAuth or API credentials in production.',
            'channel' => $this->channelPayload($row->fresh()),
        ]);
    }

    public function available(Request $request): JsonResponse
    {
        $this->ensureRowsForUser($request);

        $order = array_keys($this->portalCatalog());

        $disconnected = BookingPortalConnection::query()
            ->where('user_id', $request->user()->id)
            ->where('is_connected', false)
            ->where('portal_key', '!=', 'direct_website')
            ->get()
            ->sortBy(fn (BookingPortalConnection $row) => array_search($row->portal_key, $order, true))
            ->values()
            ->map(fn (BookingPortalConnection $row) => $this->channelPayload($row))
            ->values();

        return response()->json(['channels' => $disconnected]);
    }

    private function assertPortalKey(string $portalKey): void
    {
        if (! array_key_exists($portalKey, $this->portalCatalog())) {
            abort(404, 'Unknown portal.');
        }
    }
}
