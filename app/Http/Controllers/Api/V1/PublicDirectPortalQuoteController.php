<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BookingPortalConnection;
use App\Models\Unit;
use App\Models\User;
use App\Support\BookingStayConflict;
use App\Support\DirectPortalPromoCode;
use App\Support\UnitStayPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PublicDirectPortalQuoteController extends Controller
{
    public function show(Request $request, string $slug): JsonResponse
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

        $validated = $request->validate([
            'unitId' => ['required', 'integer'],
            'checkIn' => ['required', 'date_format:Y-m-d'],
            'checkOut' => ['required', 'date_format:Y-m-d', 'after:checkIn'],
            'unitCount' => ['sometimes', 'integer', 'min:1', 'max:'.BookingStayConflict::MAX_PORTAL_UNITS_PER_BOOKING],
            'promoCode' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $unit = Unit::query()
            ->where('user_id', $user->id)
            ->whereKey($validated['unitId'])
            ->where('status', 'active')
            ->first();

        if ($unit === null) {
            return response()->json(['message' => 'This unit is not available for booking.'], 422);
        }

        $checkIn = Carbon::createFromFormat('Y-m-d', $validated['checkIn'])->startOfDay();
        $checkOut = Carbon::createFromFormat('Y-m-d', $validated['checkOut'])->startOfDay();

        $unitCount = (int) ($validated['unitCount'] ?? 1);
        if ($unit->property_id === null && $unitCount > 1) {
            return response()->json(['message' => 'Only one unit can be booked for this listing.'], 422);
        }

        $bookUnits = BookingStayConflict::resolveDirectPortalBookUnits($user->id, $unit, $checkIn, $checkOut, $unitCount);
        if (count($bookUnits) < $unitCount) {
            $have = count($bookUnits);

            return response()->json([
                'message' => $have === 0
                    ? BookingStayConflict::guestPortalUnavailableMessage()
                    : BookingStayConflict::guestPortalInsufficientUnitsMessage($have, $unitCount),
            ], 422);
        }

        $sum = 0.0;
        $currency = null;
        $nights = null;
        foreach ($bookUnits as $bookUnit) {
            $pricing = UnitStayPricing::computeForStay($bookUnit, $checkIn, $checkOut);
            if ($pricing['error'] !== null) {
                return response()->json(['message' => $pricing['error']], 422);
            }
            $sum += (float) $pricing['total'];
            $currency = $pricing['currency'];
            $nights = $pricing['nights'];
        }

        $subtotal = round($sum, 2);
        $promo = DirectPortalPromoCode::resolve(
            (int) $user->id,
            $validated['promoCode'] ?? null,
            (int) $nights,
            $subtotal,
        );
        if (! $promo['ok']) {
            return response()->json(['message' => $promo['message']], 422);
        }

        return response()->json([
            'subtotalPrice' => $subtotal,
            'discountAmount' => (float) $promo['discountAmount'],
            'totalPrice' => (float) $promo['discountedTotal'],
            'currency' => (string) $currency,
            'nights' => (int) $nights,
            'unitCount' => count($bookUnits),
            'promoCode' => $promo['promo']?->code,
        ]);
    }
}
