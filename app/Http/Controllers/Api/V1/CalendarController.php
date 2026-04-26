<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\UnitDateBlock;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after:from'],
        ]);

        $from = Carbon::createFromFormat('Y-m-d', $validated['from'])->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', $validated['to'])->startOfDay();
        if ($from->diffInDays($to) > 400) {
            return response()->json(['message' => 'Date range is too large (max 400 days).'], 422);
        }

        $userId = $request->user()->id;

        $units = Unit::query()
            ->with('property:id,property_name')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderBy('property_id')
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'property_id', 'name', 'type', 'max_guests', 'bedrooms', 'beds', 'price_per_night', 'currency']);

        $unitIds = $units->pluck('id')->all();
        if ($unitIds === []) {
            return response()->json([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'units' => [],
            ]);
        }

        $bookings = Booking::query()
            ->where('user_id', $userId)
            ->whereIn('unit_id', $unitIds)
            ->where('check_in', '<', $to->toDateString())
            ->where('check_out', '>', $from->toDateString())
            ->orderBy('check_in')
            ->withSum('payments', 'amount')
            ->get([
                'id',
                'unit_id',
                'reference',
                'guest_name',
                'check_in',
                'check_out',
                'status',
                'source',
                'total_price',
                'currency',
            ]);

        $blocks = UnitDateBlock::query()
            ->where('user_id', $userId)
            ->whereIn('unit_id', $unitIds)
            ->where('start_date', '<', $to->toDateString())
            ->where('end_date', '>', $from->toDateString())
            ->orderBy('start_date')
            ->get(['id', 'unit_id', 'start_date', 'end_date', 'label', 'notes']);

        $bookingsByUnit = $bookings->groupBy('unit_id');
        $blocksByUnit = $blocks->groupBy('unit_id');

        $payload = $units->map(static function (Unit $unit) use ($bookingsByUnit, $blocksByUnit): array {
            $bRows = $bookingsByUnit->get($unit->id, collect());
            $kRows = $blocksByUnit->get($unit->id, collect());

            return [
                'id' => $unit->id,
                'propertyId' => $unit->property_id,
                'propertyName' => $unit->property?->property_name,
                'name' => $unit->name,
                'type' => $unit->type,
                'maxGuests' => $unit->max_guests,
                'bedrooms' => $unit->bedrooms,
                'beds' => $unit->beds,
                'pricePerNight' => (float) $unit->price_per_night,
                'currency' => $unit->currency,
                'bookings' => $bRows->map(static function (Booking $b): array {
                    $total = round((float) $b->total_price, 2);
                    $paid = round((float) ($b->payments_sum_amount ?? 0), 2);
                    $balanceDue = round(max(0, $total - $paid), 2);

                    return [
                        'id' => $b->id,
                        'reference' => $b->reference,
                        'guestName' => $b->guest_name,
                        'checkIn' => $b->check_in?->format('Y-m-d'),
                        'checkOut' => $b->check_out?->format('Y-m-d'),
                        'status' => $b->status,
                        'source' => $b->source,
                        'totalPrice' => $total,
                        'currency' => $b->currency,
                        'balanceDue' => $balanceDue,
                    ];
                })->values()->all(),
                'blocks' => $kRows->map(static function (UnitDateBlock $k): array {
                    return [
                        'id' => $k->id,
                        'startDate' => $k->start_date?->format('Y-m-d'),
                        'endDate' => $k->end_date?->format('Y-m-d'),
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
