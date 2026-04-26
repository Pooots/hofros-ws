<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPortalConnection;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PublicDirectPortalCalendarController extends Controller
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
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after:from'],
            'unitId' => ['nullable', 'integer'],
        ]);

        $from = Carbon::createFromFormat('Y-m-d', $validated['from'])->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', $validated['to'])->startOfDay();
        if ($from->diffInDays($to) > 400) {
            return response()->json(['message' => 'Date range is too large (max 400 days).'], 422);
        }

        $unitsQuery = Unit::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('type')
            ->orderBy('name');

        if (array_key_exists('unitId', $validated) && $validated['unitId'] !== null) {
            $unitsQuery->whereKey($validated['unitId']);
        }

        $units = $unitsQuery->get(['id', 'name', 'type', 'price_per_night', 'currency']);
        $unitIds = $units->pluck('id')->all();

        if ($unitIds === []) {
            if (array_key_exists('unitId', $validated) && $validated['unitId'] !== null) {
                return response()->json(['message' => 'Unit not found.'], 404);
            }

            return response()->json([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'units' => [],
            ]);
        }

        $bookings = Booking::query()
            ->where('user_id', $user->id)
            ->whereIn('unit_id', $unitIds)
            ->whereIn('status', [
                Booking::STATUS_ACCEPTED,
                Booking::STATUS_ASSIGNED,
                Booking::STATUS_CHECKED_IN,
                Booking::STATUS_CHECKED_OUT,
            ])
            ->where('check_in', '<', $to->toDateString())
            ->where('check_out', '>', $from->toDateString())
            ->orderBy('check_in')
            ->get(['id', 'unit_id', 'reference', 'guest_name', 'check_in', 'check_out', 'status', 'source']);

        $blocks = UnitDateBlock::query()
            ->where('user_id', $user->id)
            ->whereIn('unit_id', $unitIds)
            ->where('start_date', '<', $to->toDateString())
            ->where('end_date', '>', $from->toDateString())
            ->orderBy('start_date')
            ->get(['id', 'unit_id', 'start_date', 'end_date', 'label']);

        $bookingsByUnit = $bookings->groupBy('unit_id');
        $blocksByUnit = $blocks->groupBy('unit_id');

        $payload = $units->map(static function (Unit $unit) use ($bookingsByUnit, $blocksByUnit): array {
            $bRows = $bookingsByUnit->get($unit->id, collect());
            $kRows = $blocksByUnit->get($unit->id, collect());

            return [
                'id' => $unit->id,
                'name' => $unit->name,
                'type' => $unit->type,
                'pricePerNight' => (float) $unit->price_per_night,
                'currency' => $unit->currency,
                'bookings' => $bRows->map(static function (Booking $b): array {
                    return [
                        'id' => $b->id,
                        'reference' => $b->reference,
                        'guestName' => $b->guest_name,
                        'checkIn' => $b->check_in?->format('Y-m-d'),
                        'checkOut' => $b->check_out?->format('Y-m-d'),
                        'status' => $b->status,
                        'source' => $b->source,
                    ];
                })->values()->all(),
                'blocks' => $kRows->map(static function (UnitDateBlock $k): array {
                    return [
                        'id' => $k->id,
                        'startDate' => $k->start_date?->format('Y-m-d'),
                        'endDate' => $k->end_date?->format('Y-m-d'),
                        'label' => $k->label,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'units' => $payload,
        ]);
    }
}
