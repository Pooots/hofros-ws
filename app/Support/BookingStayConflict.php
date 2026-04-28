<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class BookingStayConflict
{
    /** Hard cap on units per direct-portal quote/booking request (abuse / payload size). */
    public const MAX_PORTAL_UNITS_PER_BOOKING = 50;

    /**
     * @param  CarbonInterface  $start  inclusive check-in date
     * @param  CarbonInterface  $end  exclusive check-out date (same semantics as bookings table)
     */
    public static function hasOverlappingBooking(string $userUuid, string $unitUuid, CarbonInterface $start, CarbonInterface $end, ?string $exceptBookingUuid): bool
    {
        $q = Booking::query()
            ->where('user_uuid', $userUuid)
            ->where('unit_uuid', $unitUuid)
            ->whereIn('status', [
                Booking::STATUS_ASSIGNED,
                Booking::STATUS_CHECKED_IN,
                Booking::STATUS_CHECKED_OUT,
            ])
            ->where('check_in', '<', $end->toDateString())
            ->where('check_out', '>', $start->toDateString());

        if ($exceptBookingUuid !== null) {
            $q->where('uuid', '!=', $exceptBookingUuid);
        }

        return $q->exists();
    }

    public static function hasOverlappingBlock(string $userUuid, string $unitUuid, CarbonInterface $start, CarbonInterface $end): bool
    {
        return UnitDateBlock::query()
            ->where('user_uuid', $userUuid)
            ->where('unit_uuid', $unitUuid)
            ->where('start_date', '<', $end->toDateString())
            ->where('end_date', '>', $start->toDateString())
            ->exists();
    }

    /**
     * True when a manual unit date block would overlap an accepted or assigned stay on that unit.
     */
    public static function blockOverlapsFirmBooking(string $userUuid, string $unitUuid, CarbonInterface $start, CarbonInterface $end): bool
    {
        return Booking::query()
            ->where('user_uuid', $userUuid)
            ->where('unit_uuid', $unitUuid)
            ->whereIn('status', [
                Booking::STATUS_ACCEPTED,
                Booking::STATUS_ASSIGNED,
                Booking::STATUS_CHECKED_IN,
                Booking::STATUS_CHECKED_OUT,
            ])
            ->where('check_in', '<', $end->toDateString())
            ->where('check_out', '>', $start->toDateString())
            ->exists();
    }

    /**
     * True when the guest direct portal must not allow a stay (blocked dates or a host-confirmed stay on this unit).
     *
     * Pending requests are ignored so abandoned or duplicate portal submissions do not block new guests; the host
     * calendar also only treats accepted/assigned stays as firm occupancy for this unit.
     */
    public static function guestPortalStayIsUnavailable(string $userUuid, string $unitUuid, CarbonInterface $start, CarbonInterface $end): bool
    {
        if (self::hasOverlappingBlock($userUuid, $unitUuid, $start, $end)) {
            return true;
        }

        return Booking::query()
            ->where('user_uuid', $userUuid)
            ->where('unit_uuid', $unitUuid)
            ->whereIn('status', [
                Booking::STATUS_ACCEPTED,
                Booking::STATUS_ASSIGNED,
                Booking::STATUS_CHECKED_IN,
                Booking::STATUS_CHECKED_OUT,
            ])
            ->where('check_in', '<', $end->toDateString())
            ->where('check_out', '>', $start->toDateString())
            ->exists();
    }

    /**
     * Picks one unit for a direct-portal stay (see {@see resolveDirectPortalUnitExcluding}).
     */
    public static function resolveDirectPortalUnit(string $userUuid, Unit $listedUnit, CarbonInterface $checkIn, CarbonInterface $checkOut): ?Unit
    {
        return self::resolveDirectPortalUnitExcluding($userUuid, $listedUnit, $checkIn, $checkOut, []);
    }

    /**
     * Same as {@see resolveDirectPortalUnit} but skips any unit uuids in {@see $excludeUnitUuids} (e.g. already picked in a multi-unit request).
     *
     * @param  list<string>  $excludeUnitUuids
     */
    public static function resolveDirectPortalUnitExcluding(
        string $userUuid,
        Unit $listedUnit,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        array $excludeUnitUuids,
    ): ?Unit {
        $exclude = array_flip(array_map(static fn (string $uuid): string => (string) $uuid, $excludeUnitUuids));

        $try = function (Unit $candidate) use ($userUuid, $checkIn, $checkOut, $exclude): ?Unit {
            $uuid = $candidate->uuid;
            if (isset($exclude[(string) $uuid])) {
                return null;
            }
            if (! self::guestPortalStayIsUnavailable($userUuid, (string) $uuid, $checkIn, $checkOut)) {
                return $candidate;
            }

            return null;
        };

        $first = $try($listedUnit);
        if ($first !== null) {
            return $first;
        }

        if ($listedUnit->property_uuid === null) {
            return null;
        }

        $candidates = Unit::query()
            ->where('user_uuid', $userUuid)
            ->where('status', 'active')
            ->where('property_uuid', $listedUnit->property_uuid)
            ->where('max_guests', $listedUnit->max_guests)
            ->where('bedrooms', $listedUnit->bedrooms)
            ->where('beds', $listedUnit->beds)
            ->where(static function (Builder $q) use ($listedUnit): void {
                if ($listedUnit->type === null) {
                    $q->whereNull('type');
                } else {
                    $q->where('type', $listedUnit->type);
                }
            })
            ->orderByDesc('created_at')
            ->get();

        foreach ($candidates as $candidate) {
            $picked = $try($candidate);
            if ($picked !== null) {
                return $picked;
            }
        }

        return null;
    }

    /**
     * Resolves distinct matching units for the same stay (direct portal), up to {@see $unitCount}.
     * Stops early when no further free unit exists; callers must compare count to {@see $unitCount}.
     *
     * @return list<Unit>
     */
    public static function resolveDirectPortalBookUnits(
        string $userUuid,
        Unit $listedUnit,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        int $unitCount,
    ): array {
        if ($unitCount < 1 || $unitCount > self::MAX_PORTAL_UNITS_PER_BOOKING) {
            return [];
        }
        if ($listedUnit->property_uuid === null && $unitCount > 1) {
            return [];
        }

        $exclude = [];
        $out = [];
        for ($i = 0; $i < $unitCount; $i++) {
            $next = self::resolveDirectPortalUnitExcluding($userUuid, $listedUnit, $checkIn, $checkOut, $exclude);
            if ($next === null) {
                break;
            }
            $out[] = $next;
            $exclude[] = $next->uuid;
        }

        return $out;
    }

    /** User-facing copy for 422 responses when no matching unit is free for the stay (see {@see resolveDirectPortalUnit}). */
    public static function guestPortalUnavailableMessage(): string
    {
        return 'These dates are fully booked for this accommodation. Please choose different check-in or check-out dates.';
    }

    /** When the guest asks for more matching rooms than are free for the stay. */
    public static function guestPortalInsufficientUnitsMessage(int $available, int $requested): string
    {
        return sprintf(
            'Only %d matching unit(s) are available for these dates; you requested %d. Reduce the number of units or choose different check-in or check-out dates.',
            $available,
            $requested
        );
    }
}
