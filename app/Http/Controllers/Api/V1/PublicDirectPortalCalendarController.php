<?php

namespace App\Http\Controllers\Api\v1;

use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Repositories\PublicDirectPortalRepository;
use App\Http\Requests\PublicDirectPortal\PublicCalendarRequest;
use App\Models\Booking;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class PublicDirectPortalCalendarController extends Controller
{
    public function __construct(protected PublicDirectPortalRepository $portalRepository)
    {
    }

    public function show(PublicCalendarRequest $request, string $slug): JsonResponse
    {
        $resolved = $this->portalRepository->resolveBySlugOrThrow($slug);
        $user = $resolved['user'];

        $validated = $request->validated();

        $from = Carbon::createFromFormat('Y-m-d', $validated['from'])->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', $validated['to'])->startOfDay();
        if ($from->diffInDays($to) > 400) {
            throw new InvalidDateRangeException();
        }

        $unitsQuery = Unit::query()
            ->where('user_uuid', $user->uuid)
            ->where('status', 'active')
            ->orderBy('type')
            ->orderBy('name');

        if (array_key_exists('unitId', $validated) && $validated['unitId'] !== null) {
            $unitsQuery->whereKey($validated['unitId']);
        }

        $units = $unitsQuery->get(['uuid', 'name', 'type', 'price_per_night', 'currency']);
        $unitUuids = $units->pluck('uuid')->all();

        if ($unitUuids === []) {
            if (array_key_exists('unitId', $validated) && $validated['unitId'] !== null) {
                throw new NotFoundException('Unit not found.');
            }

            return response()->json([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'units' => [],
            ]);
        }

        $bookings = Booking::query()
            ->where('user_uuid', $user->uuid)
            ->whereIn('unit_uuid', $unitUuids)
            ->whereIn('status', [
                Booking::STATUS_ACCEPTED,
                Booking::STATUS_ASSIGNED,
                Booking::STATUS_CHECKED_IN,
                Booking::STATUS_CHECKED_OUT,
            ])
            ->where('check_in', '<', $to->toDateString())
            ->where('check_out', '>', $from->toDateString())
            ->orderBy('check_in')
            ->get(['uuid', 'unit_uuid', 'reference', 'guest_name', 'check_in', 'check_out', 'status', 'source']);

        $blocks = UnitDateBlock::query()
            ->where('user_uuid', $user->uuid)
            ->whereIn('unit_uuid', $unitUuids)
            ->where('start_date', '<', $to->toDateString())
            ->where('end_date', '>', $from->toDateString())
            ->orderBy('start_date')
            ->get(['uuid', 'unit_uuid', 'start_date', 'end_date', 'label']);

        $bookingsByUnit = $bookings->groupBy('unit_uuid');
        $blocksByUnit = $blocks->groupBy('unit_uuid');

        $payload = $units->map(static function (Unit $unit) use ($bookingsByUnit, $blocksByUnit): array {
            $bRows = $bookingsByUnit->get($unit->uuid, collect());
            $kRows = $blocksByUnit->get($unit->uuid, collect());

            return [
                'uuid' => $unit->uuid,
                'name' => $unit->name,
                'type' => $unit->type,
                'pricePerNight' => (float) $unit->price_per_night,
                'currency' => $unit->currency,
                'bookings' => $bRows->map(static function (Booking $b): array {
                    return [
                        'uuid' => $b->uuid,
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
                        'uuid' => $k->uuid,
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
