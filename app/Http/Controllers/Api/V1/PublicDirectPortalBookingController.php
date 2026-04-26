<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPortalConnection;
use App\Models\PromoCode;
use App\Models\Unit;
use App\Models\User;
use App\Support\BookingStayConflict;
use App\Support\DirectPortalPromoCode;
use App\Support\UnitStayPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicDirectPortalBookingController extends Controller
{
    public function store(Request $request, string $slug): JsonResponse
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
            'guestName' => ['required', 'string', 'max:255'],
            'guestEmail' => ['required', 'string', 'email', 'max:255'],
            'guestPhone' => ['required', 'string', 'max:64'],
            'checkIn' => ['required', 'date_format:Y-m-d'],
            'checkOut' => ['required', 'date_format:Y-m-d', 'after:checkIn'],
            'adults' => ['required', 'integer', 'min:1', 'max:500'],
            'children' => ['required', 'integer', 'min:0', 'max:500'],
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

        $adults = (int) $validated['adults'];
        $children = (int) $validated['children'];

        $digits = preg_replace('/\D+/', '', (string) $validated['guestPhone']) ?? '';
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return response()->json(['message' => 'Please enter a valid mobile number (8–15 digits).'], 422);
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

        foreach ($bookUnits as $bookUnit) {
            $maxGuests = (int) $bookUnit->max_guests;
            if ($adults + $children > $maxGuests) {
                return response()->json(['message' => 'Guest count exceeds the maximum for this unit.'], 422);
            }
        }

        $pricedRows = [];
        $subtotal = 0.0;
        $nights = null;
        foreach ($bookUnits as $bookUnit) {
            $pricing = UnitStayPricing::computeForStay($bookUnit, $checkIn, $checkOut);
            if ($pricing['error'] !== null) {
                return response()->json(['message' => $pricing['error']], 422);
            }
            $subtotal += (float) $pricing['total'];
            $nights = $pricing['nights'];
            $pricedRows[] = [
                'unit' => $bookUnit,
                'total' => $pricing['total'],
            ];
        }

        $promo = DirectPortalPromoCode::resolve(
            (int) $user->id,
            $validated['promoCode'] ?? null,
            (int) $nights,
            round($subtotal, 2),
        );
        if (! $promo['ok']) {
            return response()->json(['message' => $promo['message']], 422);
        }

        $discountAmount = (float) $promo['discountAmount'];
        $promoEntity = $promo['promo'];
        if ($discountAmount > 0.0 && $subtotal > 0.0) {
            $remaining = $discountAmount;
            $count = count($pricedRows);
            foreach ($pricedRows as $idx => &$row) {
                $raw = (float) $row['total'];
                $share = $idx === $count - 1
                    ? $remaining
                    : round($discountAmount * ($raw / $subtotal), 2);
                $share = min($remaining, max(0.0, $share));
                $row['total'] = round(max(0.0, $raw - $share), 2);
                $remaining = round(max(0.0, $remaining - $share), 2);
            }
            unset($row);
            if ($remaining > 0.0 && $count > 0) {
                $last = $count - 1;
                $pricedRows[$last]['total'] = round(max(0.0, (float) $pricedRows[$last]['total'] - $remaining), 2);
            }
        }

        $batchId = (string) Str::uuid();
        $multi = count($bookUnits) > 1;

        try {
            $created = DB::transaction(function () use ($user, $pricedRows, $checkIn, $checkOut, $adults, $children, $validated, $batchId, $multi, $promoEntity): array {
                $out = [];
                foreach ($pricedRows as $row) {
                    $bookUnit = $row['unit'];
                    $total = $row['total'];
                    $reference = $this->uniqueReference();

                    $out[] = Booking::create([
                        'user_id' => $user->id,
                        'unit_id' => $bookUnit->id,
                        'reference' => $reference,
                        'guest_name' => $validated['guestName'],
                        'guest_email' => $validated['guestEmail'],
                        'guest_phone' => $validated['guestPhone'],
                        'check_in' => $checkIn->toDateString(),
                        'check_out' => $checkOut->toDateString(),
                        'adults' => $adults,
                        'children' => $children,
                        'total_price' => $total,
                        'currency' => $bookUnit->currency,
                        'source' => Booking::SOURCE_DIRECT_PORTAL,
                        'status' => Booking::STATUS_PENDING,
                        'notes' => null,
                        'portal_batch_id' => $multi ? $batchId : null,
                    ]);
                }

                if ($promoEntity !== null) {
                    $updated = PromoCode::query()
                        ->where('id', $promoEntity->id)
                        ->where('user_id', $user->id)
                        ->where('status', PromoCode::STATUS_ACTIVE)
                        ->where(static function ($q): void {
                            $q->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses');
                        })
                        ->update(['uses_count' => DB::raw('uses_count + 1')]);
                    if ($updated < 1) {
                        throw new \RuntimeException('Promo code has reached its usage limit.');
                    }
                }

                return $out;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->toPublicPayload(
            $created,
            round($subtotal, 2),
            $discountAmount,
            $promoEntity?->code
        ), 201);
    }

    private function uniqueReference(): string
    {
        for ($i = 0; $i < 12; $i++) {
            $candidate = 'HFR-'.Str::upper(Str::random(8));
            if (! Booking::query()->where('reference', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'HFR-'.Str::upper(Str::uuid()->toString());
    }

    /**
     * @param  list<Booking>  $bookings
     * @return array<string, mixed>
     */
    private function toPublicPayload(array $bookings, float $subtotal, float $discountAmount, ?string $promoCode): array
    {
        $first = $bookings[0];
        $refs = array_map(static fn (Booking $b): string => (string) $b->reference, $bookings);
        $totalAmount = round(array_sum(array_map(static fn (Booking $b): float => (float) $b->total_price, $bookings)), 2);

        return [
            'reference' => $first->reference,
            'references' => $refs,
            'unitCount' => count($bookings),
            'subtotalAmount' => round($subtotal, 2),
            'discountAmount' => round($discountAmount, 2),
            'totalAmount' => $totalAmount,
            'promoCode' => $promoCode,
            'portalBatchId' => $first->portal_batch_id,
            'status' => $first->status,
            'message' => count($bookings) > 1
                ? 'Your booking requests were recorded. The host will follow up to confirm.'
                : 'Your booking request was recorded. The host will follow up to confirm.',
        ];
    }
}
