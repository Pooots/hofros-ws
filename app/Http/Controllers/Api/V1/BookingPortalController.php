<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BookingPortalException;
use App\Http\Controllers\Controller;
use App\Http\Repositories\BookingPortalRepository;
use App\Http\Requests\BookingPortal\SaveDirectWebsiteContentRequest;
use App\Http\Requests\BookingPortal\SaveDirectWebsiteDesignRequest;
use App\Http\Requests\BookingPortal\SetDirectWebsiteLiveRequest;
use App\Http\Requests\BookingPortal\UpdatePortalActiveRequest;
use App\Http\Requests\BookingPortal\UploadHeroRequest;
use App\Http\Resources\BookingPortalChannelResource;
use App\Models\BookingPortalConnection;
use App\Support\GuestPortalLayout;
use App\Support\GuestPortalUnits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BookingPortalController extends Controller
{
    public function __construct(protected BookingPortalRepository $portalRepository)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $userUuid = $request->user()->uuid;
        $merchantSlug = Str::slug((string) ($request->user()->merchant_name ?? '')) ?: 'merchant';

        $order = array_keys($this->portalRepository->portalCatalog());

        $rows = $this->portalRepository
            ->getAllForUser($userUuid)
            ->sortBy(fn (BookingPortalConnection $row) => array_search($row->portal_key, $order, true))
            ->values();

        $channels = $rows->map(
            fn (BookingPortalConnection $row): array => (new BookingPortalChannelResource($row))
                ->additional(['merchantSlug' => $merchantSlug])
                ->resolve($request)
        )->values();

        $connected = $rows->filter(function (BookingPortalConnection $r): bool {
            if ($r->portal_key === BookingPortalRepository::DIRECT_WEBSITE_KEY) {
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
        $row = $this->portalRepository->directWebsiteRow($user->uuid);

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
            'units' => GuestPortalUnits::publicPayloadForUserUuid($user->uuid),
        ]);
    }

    public function uploadDirectWebsiteHero(UploadHeroRequest $request): JsonResponse
    {
        $row = $this->portalRepository->directWebsiteRow($request->user()->uuid);
        $file = $request->file('image');

        $dir = 'guest-portal-heroes/'.$request->user()->uuid;
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

    public function saveDirectWebsiteContent(SaveDirectWebsiteContentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $row = $this->portalRepository->directWebsiteRow($request->user()->uuid);

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

        $this->applyGuestPortalVisualFromValidated($row, $validated);
        $this->applyGuestPortalLayoutFromValidated($row, $validated);

        return response()->json([
            'message' => 'Draft saved.',
            'channel' => (new BookingPortalChannelResource($row->fresh()))->resolve($request),
        ]);
    }

    public function saveDirectWebsiteDesign(SaveDirectWebsiteDesignRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $row = $this->portalRepository->directWebsiteRow($request->user()->uuid);

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
        $this->applyGuestPortalVisualFromValidated($row, $validated);
        $this->applyGuestPortalLayoutFromValidated($row, $validated);

        return response()->json([
            'message' => 'Micro-site design saved. You can go live from the website builder when you are ready.',
            'channel' => (new BookingPortalChannelResource($row->fresh()))->resolve($request),
        ]);
    }

    public function setDirectWebsiteLive(SetDirectWebsiteLiveRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $row = $this->portalRepository->directWebsiteRow($request->user()->uuid);

        if ($validated['live'] && ! $row->guest_portal_design_completed) {
            throw new BookingPortalException('Complete your micro-site design before publishing the guest link.');
        }

        if ($validated['live'] && (! is_string($row->guest_portal_headline) || trim($row->guest_portal_headline) === '')) {
            throw new BookingPortalException('Add a headline in the micro-site designer before going live.');
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
            'message' => $live
                ? 'Direct portal is now live for guests.'
                : 'Direct portal is in testing mode. Guests no longer see the public page.',
            'channel' => (new BookingPortalChannelResource($row->fresh()))->resolve($request),
        ]);
    }

    public function updateActive(UpdatePortalActiveRequest $request, string $portalKey): JsonResponse
    {
        $this->assertPortalKey($portalKey);

        $validated = $request->validated();
        $row = $this->portalRepository->findOrFail($request->user()->uuid, $portalKey);

        if (! $row->is_connected) {
            throw new BookingPortalException('Connect this channel before enabling it.');
        }

        $row->update(['is_active' => $validated['isActive']]);

        return response()->json((new BookingPortalChannelResource($row->fresh()))->resolve($request));
    }

    public function sync(Request $request, string $portalKey): JsonResponse
    {
        $this->assertPortalKey($portalKey);

        $row = $this->portalRepository->findOrFail($request->user()->uuid, $portalKey);

        if (! $row->is_connected) {
            throw new BookingPortalException('Connect this channel before syncing.');
        }

        $row->update([
            'last_synced_at' => Carbon::now(),
            'has_sync_issue' => false,
        ]);

        return response()->json([
            'message' => 'Sync queued for '.$portalKey.'.',
            'channel' => (new BookingPortalChannelResource($row->fresh()))->resolve($request),
        ]);
    }

    public function connect(Request $request, string $portalKey): JsonResponse
    {
        $this->assertPortalKey($portalKey);

        if ($portalKey === BookingPortalRepository::DIRECT_WEBSITE_KEY) {
            throw new BookingPortalException('Use “Build a direct website” in the app to open the website builder and copy your guest link.');
        }

        $row = $this->portalRepository->findOrFail($request->user()->uuid, $portalKey);

        if ($row->is_connected) {
            return response()->json([
                'message' => 'This channel is already connected.',
                'channel' => (new BookingPortalChannelResource($row))->resolve($request),
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
            'channel' => (new BookingPortalChannelResource($row->fresh()))->resolve($request),
        ]);
    }

    public function available(Request $request): JsonResponse
    {
        $order = array_keys($this->portalRepository->portalCatalog());

        $disconnected = $this->portalRepository
            ->getDisconnectedForUser($request->user()->uuid)
            ->sortBy(fn (BookingPortalConnection $row) => array_search($row->portal_key, $order, true))
            ->values()
            ->map(fn (BookingPortalConnection $row): array => (new BookingPortalChannelResource($row))->resolve($request))
            ->values();

        return response()->json(['channels' => $disconnected]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyGuestPortalVisualFromValidated(BookingPortalConnection $row, array $validated): void
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

    private function defaultGuestPortalHeroUrl(): string
    {
        return 'https://images.unsplash.com/photo-1566073771259-6a850eaba8c9?auto=format&fit=crop&w=1600&q=80';
    }

    private function assertPortalKey(string $portalKey): void
    {
        if (! array_key_exists($portalKey, $this->portalRepository->portalCatalog())) {
            abort(404, 'Unknown portal.');
        }
    }
}
