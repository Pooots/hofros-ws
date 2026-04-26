<?php

namespace App\Support;

use App\Models\Unit;

final class GuestPortalUnits
{
    /**
     * Active units for a merchant, safe for public / guest portal JSON.
     *
     * @return list<array{id: int, name: string, type: string, maxGuests: int, bedrooms: int|null, beds: int|null, pricePerNight: float, pricePerNightMax: float, currency: string, propertyId: int|null, propertyName: string|null, description: string|null, details: string|null, images: list<string>, weekSchedule: array<string, bool>}>
     */
    public static function publicPayloadForUserId(int $userId): array
    {
        return Unit::query()
            ->with(['property:id,property_name', 'rateIntervals'])
            ->where('user_id', $userId)
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
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'type' => $unit->type,
                    'maxGuests' => $unit->max_guests,
                    'bedrooms' => $unit->bedrooms,
                    'beds' => $unit->beds,
                    'pricePerNight' => $nightly['min'],
                    'pricePerNightMax' => $nightly['max'],
                    'currency' => $unit->currency,
                    'propertyId' => $unit->property_id !== null ? (int) $unit->property_id : null,
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
