<?php

namespace App\Http\Controllers\Api\v1;

use App\Exceptions\InvalidDateRangeException;
use App\Http\Controllers\Controller;
use App\Http\Repositories\CalendarRepository;
use App\Http\Requests\Calendar\ListCalendarRequest;
use App\Models\Booking;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    public function __construct(protected CalendarRepository $calendarRepository)
    {
    }

    public function index(ListCalendarRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $from = Carbon::createFromFormat('Y-m-d', $validated['from'])->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', $validated['to'])->startOfDay();
        if ($from->diffInDays($to) > 400) {
            throw new InvalidDateRangeException();
        }

        $userUuid = $request->user()->uuid;

        $units = $this->calendarRepository->getUnitsForUser($userUuid);
        $unitUuids = $units->pluck('uuid')->all();

        if ($unitUuids === []) {
            return response()->json([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'units' => [],
            ]);
        }

        $bookings = $this->calendarRepository->getBookingsInRange($userUuid, $unitUuids, $from, $to);
        $blocks = $this->calendarRepository->getBlocksInRange($userUuid, $unitUuids, $from, $to);

        $bookingsByUnit = $bookings->groupBy('unit_uuid');
        $blocksByUnit = $blocks->groupBy('unit_uuid');

        $payload = $units->map(static function (Unit $unit) use ($bookingsByUnit, $blocksByUnit): array {
            $bRows = $bookingsByUnit->get($unit->uuid, collect());
            $kRows = $blocksByUnit->get($unit->uuid, collect());

            return [
                'uuid' => $unit->uuid,
                'property_uuid' => $unit->property_uuid,
                'property_name' => $unit->property?->property_name,
                'name' => $unit->name,
                'type' => $unit->type,
                'max_guests' => $unit->max_guests,
                'bedrooms' => $unit->bedrooms,
                'beds' => $unit->beds,
                'price_per_night' => (float) $unit->price_per_night,
                'currency' => $unit->currency,
                'bookings' => $bRows->map(static function (Booking $b): array {
                    $total = round((float) $b->total_price, 2);
                    $paid = round((float) ($b->payments_sum_amount ?? 0), 2);
                    $balanceDue = round(max(0, $total - $paid), 2);

                    return [
                        'uuid' => $b->uuid,
                        'reference' => $b->reference,
                        'guest_name' => $b->guest_name,
                        'check_in' => $b->check_in?->format('Y-m-d'),
                        'check_out' => $b->check_out?->format('Y-m-d'),
                        'status' => $b->status,
                        'source' => $b->source,
                        'total_price' => $total,
                        'currency' => $b->currency,
                        'balance_due' => $balanceDue,
                    ];
                })->values()->all(),
                'blocks' => $kRows->map(static function (UnitDateBlock $k): array {
                    return [
                        'uuid' => $k->uuid,
                        'start_date' => $k->start_date?->format('Y-m-d'),
                        'end_date' => $k->end_date?->format('Y-m-d'),
                        'label' => $k->label,
                        'notes' => $k->notes,
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
