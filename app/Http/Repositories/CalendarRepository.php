<?php

namespace App\Http\Repositories;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class CalendarRepository
{
    /**
     * @return Collection<int, Unit>
     */
    public function getUnitsForUser(string $userUuid): Collection
    {
        return Unit::query()
            ->with('property:uuid,property_name')
            ->where('user_uuid', $userUuid)
            ->where('status', 'active')
            ->orderBy('property_uuid')
            ->orderBy('type')
            ->orderBy('name')
            ->get(['uuid', 'property_uuid', 'name', 'type', 'max_guests', 'bedrooms', 'beds', 'price_per_night', 'currency']);
    }

    /**
     * @param  array<int, string>  $unitUuids
     * @return Collection<int, Booking>
     */
    public function getBookingsInRange(string $userUuid, array $unitUuids, Carbon $from, Carbon $to): Collection
    {
        return Booking::query()
            ->where('user_uuid', $userUuid)
            ->whereIn('unit_uuid', $unitUuids)
            ->where('check_in', '<', $to->toDateString())
            ->where('check_out', '>', $from->toDateString())
            ->orderBy('check_in')
            ->withSum('payments', 'amount')
            ->get([
                'uuid',
                'unit_uuid',
                'reference',
                'guest_name',
                'check_in',
                'check_out',
                'status',
                'source',
                'total_price',
                'currency',
            ]);
    }

    /**
     * @param  array<int, string>  $unitUuids
     * @return Collection<int, UnitDateBlock>
     */
    public function getBlocksInRange(string $userUuid, array $unitUuids, Carbon $from, Carbon $to): Collection
    {
        return UnitDateBlock::query()
            ->where('user_uuid', $userUuid)
            ->whereIn('unit_uuid', $unitUuids)
            ->where('start_date', '<', $to->toDateString())
            ->where('end_date', '>', $from->toDateString())
            ->orderBy('start_date')
            ->get(['uuid', 'unit_uuid', 'start_date', 'end_date', 'label', 'notes']);
    }
}
