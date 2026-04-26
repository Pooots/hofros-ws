<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BookingPortalConnection;
use App\Models\User;
use App\Support\GuestPortalLayout;
use App\Support\GuestPortalUnits;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PublicDirectPortalController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $normalized = Str::lower(trim($slug));

        $user = User::query()
            ->whereNotNull('merchant_name')
            ->get()
            ->first(function (User $u) use ($normalized): bool {
                $candidate = Str::slug((string) $u->merchant_name) ?: 'merchant';

                return $candidate === $normalized;
            });

        if ($user === null) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $row = BookingPortalConnection::query()
            ->where('user_id', $user->id)
            ->where('portal_key', 'direct_website')
            ->first();

        if ($row === null || ! $row->guest_portal_live) {
            return response()->json(['message' => 'This booking link is not published yet.'], 404);
        }

        return response()->json([
            'slug' => $normalized,
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
            'units' => GuestPortalUnits::publicPayloadForUserId($user->id),
        ]);
    }
}
