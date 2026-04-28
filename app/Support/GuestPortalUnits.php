<?php

namespace App\Support;

use App\Models\Unit;

final class GuestPortalUnits
{
    /**
     * Active units for a merchant, safe for public / guest portal JSON.
     *
     * @return list<array{uuid: string, name: string, type: string, maxGuests: int, bedrooms: int|null, beds: int|null, pricePerNight: float, pricePerNightMax: float, currency: string, propertyId: string|null, propertyName: string|null, description: string|null, details: string|null, images: list<string>, weekSchedule: array<string, bool>}>
     */
    public static function publicPayloadForUserUuid(string $userUuid): array
    {
        return Unit::query()
            ->with(['property:uuid,property_name', 'rateIntervals'])
            ->where('user_uuid', $userUuid)
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(static function (Unit $unit): array {
                $images = is_array($unit->images) ? $unit->images : [];
                $imageUrls = array_values(array_filter($images, static fn ($u): bool => is_string($u) && $u !== ''));
                $defaults = Unit::defaultWeekSchedule();
                $week = array_merge($defaults, is_array($unit->week_schedule) ? array_intersect_key($unit->week_schedule, $defaults) : []);
                $nightly = UnitStayPricing::displayMinMaxNightlyForPortal($unit);

                return [
                    'uuid' => $unit->uuid,
                    'name' => $unit->name,
                    'type' => $unit->type,
                    'maxGuests' => $unit->max_guests,
                    'bedrooms' => $unit->bedrooms,
                    'beds' => $unit->beds,
                    'pricePerNight' => $nightly['min'],
                    'pricePerNightMax' => $nightly['max'],
                    'currency' => $unit->currency,
                    'propertyId' => $unit->property_uuid,
                    'propertyName' => $unit->property?->property_name,
                    'description' => $unit->description,
                    'details' => $unit->details,
                    'images' => $imageUrls,
                    'weekSchedule' => $week,
                ];
            })
            ->values()
            ->all();
    }
}
