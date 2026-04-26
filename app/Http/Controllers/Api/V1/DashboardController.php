<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use App\Support\MerchantBookingAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'outlookStart' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $userId = (int) $request->user()->id;
        $today = isset($validated['date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['date'])->startOfDay()
            : Carbon::today();
        $tomorrow = $today->copy()->addDay();

        $outlookStart = isset($validated['outlookStart'])
            ? Carbon::createFromFormat('Y-m-d', $validated['outlookStart'])->startOfDay()
            : $today->copy();
        $outlookEndDay = $outlookStart->copy()->addDays(13)->startOfDay();

        $totalUnits = MerchantBookingAnalytics::activeUnitsCount($userId);

        $rangeStart = $today->copy()->min($outlookStart)->min($tomorrow);
        $rangeEnd = $outlookEndDay->copy()->max($tomorrow);

        $bookingsOverlapRange = Booking::query()
            ->where('user_id', $userId)
            ->whereDate('check_in', '<=', $rangeEnd->toDateString())
            ->whereDate('check_out', '>', $rangeStart->toDateString())
            ->with($this->bookingUnitEagerLoad())
            ->get();

        $blocksOverlapRange = UnitDateBlock::query()
            ->where('user_id', $userId)
            ->whereDate('start_date', '<=', $rangeEnd->toDateString())
            ->whereDate('end_date', '>', $rangeStart->toDateString())
            ->get(['id', 'unit_id', 'start_date', 'end_date']);

        $qualifying = $bookingsOverlapRange->filter(
            fn (Booking $b) => $b->status !== Booking::STATUS_CANCELLED
        )->values();

        $kpis = $this->buildKpis($today, $qualifying, $totalUnits);

        $reservations = $this->buildReservationLists($today, $tomorrow, $qualifying);

        $bookedToday = Booking::query()
            ->where('user_id', $userId)
            ->whereDate('created_at', $today->toDateString())
            ->with($this->bookingUnitEagerLoad())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        /** Created today: pending, accepted, and assigned (excludes cancelled). */
        $newBookingsToday = $bookedToday->filter(
            fn (Booking $b) => in_array($b->status, [
                Booking::STATUS_PENDING,
                Booking::STATUS_ACCEPTED,
                Booking::STATUS_ASSIGNED,
            ], true)
        )->sort(fn (Booking $a, Booking $b): int => $this->compareNewBookingsToday($a, $b))
            ->values();

        $reservations['newToday'] = [
            'rows' => $newBookingsToday
                ->map(fn (Booking $b): array => $this->reservationRow($b, $this->newBookingsStatusLabel($b)))
                ->values()
                ->all(),
        ];

        $kpis['newlyBookedToday'] = $newBookingsToday->count();

        $cancellationsToday = Booking::query()
            ->where('user_id', $userId)
            ->where('status', Booking::STATUS_CANCELLED)
            ->whereDate('updated_at', $today->toDateString())
            ->with($this->bookingUnitEagerLoad())
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $todayActivity = [
            'sales' => $this->buildSalesActivity($bookedToday),
            'cancellations' => [
                'rows' => $cancellationsToday->map(fn (Booking $b) => $this->cancellationRow($b))->values()->all(),
            ],
            'overbookings' => [
                'rows' => $this->findOverbookingRows($userId),
            ],
        ];

        $outlook = $this->buildOutlook(
            $outlookStart,
            $outlookEndDay,
            $qualifying,
            $blocksOverlapRange,
            $totalUnits
        );

        $defaultCurrency = Unit::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->value('currency') ?? MerchantBookingAnalytics::CURRENCY_CODE;

        return response()->json([
            'date' => $today->toDateString(),
            'currency' => $defaultCurrency,
            'kpis' => $kpis,
            'reservations' => $reservations,
            'todayActivity' => $todayActivity,
            'outlook' => $outlook,
        ]);
    }

    /**
     * @param  Collection<int, Booking>  $qualifying
     * @return array<string, mixed>
     */
    private function buildKpis(Carbon $today, Collection $qualifying, int $totalUnits): array
    {
        $d = $today->toDateString();

        $arrivals = $qualifying->filter(
            fn (Booking $b) => $b->check_in?->toDateString() === $d
        )->count();

        $departures = $qualifying->filter(
            fn (Booking $b) => $b->check_out?->toDateString() === $d
        )->count();

        $occupiedUnitIds = $qualifying
            ->filter(fn (Booking $b) => $this->stayCoversDate($b, $today))
            ->pluck('unit_id')
            ->unique()
            ->values();

        $accommodationsBooked = $occupiedUnitIds->count();
        $accommodationsBookedPct = $totalUnits > 0
            ? round(($accommodationsBooked / $totalUnits) * 100, 1)
            : 0.0;

        return [
            'arrivals' => $arrivals,
            'departures' => $departures,
            'accommodationsBooked' => $accommodationsBooked,
            'accommodationsBookedPct' => $accommodationsBookedPct,
            'totalActiveUnits' => $totalUnits,
        ];
    }

    /**
     * @param  Collection<int, Booking>  $qualifying
     * @return array<string, mixed>
     */
    private function buildReservationLists(Carbon $today, Carbon $tomorrow, Collection $qualifying): array
    {
        $t0 = $today->toDateString();
        $t1 = $tomorrow->toDateString();

        $arrivalsToday = $qualifying->filter(fn (Booking $b) => $b->check_in?->toDateString() === $t0);
        $arrivalsTomorrow = $qualifying->filter(fn (Booking $b) => $b->check_in?->toDateString() === $t1);

        $departuresToday = $qualifying->filter(fn (Booking $b) => $b->check_out?->toDateString() === $t0);
        $departuresTomorrow = $qualifying->filter(fn (Booking $b) => $b->check_out?->toDateString() === $t1);

        $stayoversToday = $qualifying->filter(
            fn (Booking $b) => $this->isStayoverOn($b, $today)
        );
        $stayoversTomorrow = $qualifying->filter(
            fn (Booking $b) => $this->isStayoverOn($b, $tomorrow)
        );

        $inHouseToday = $qualifying->filter(fn (Booking $b) => $this->stayCoversDate($b, $today));
        $inHouseTomorrow = $qualifying->filter(fn (Booking $b) => $this->stayCoversDate($b, $tomorrow));

        return [
            'arrivals' => [
                'today' => $arrivalsToday->map(fn (Booking $b) => $this->reservationRow($b, 'Arrival'))->values()->all(),
                'tomorrow' => $arrivalsTomorrow->map(fn (Booking $b) => $this->reservationRow($b, 'Arrival'))->values()->all(),
            ],
            'departures' => [
                'today' => $departuresToday->map(fn (Booking $b) => $this->reservationRow($b, 'Departure'))->values()->all(),
                'tomorrow' => $departuresTomorrow->map(fn (Booking $b) => $this->reservationRow($b, 'Departure'))->values()->all(),
            ],
            'stayovers' => [
                'today' => $stayoversToday->map(fn (Booking $b) => $this->reservationRow($b, 'Stayover'))->values()->all(),
                'tomorrow' => $stayoversTomorrow->map(fn (Booking $b) => $this->reservationRow($b, 'Stayover'))->values()->all(),
            ],
            'inHouse' => [
                'today' => $inHouseToday->map(fn (Booking $b) => $this->reservationRow($b, 'In-house'))->values()->all(),
                'tomorrow' => $inHouseTomorrow->map(fn (Booking $b) => $this->reservationRow($b, 'In-house'))->values()->all(),
            ],
        ];
    }

    /**
     * @param  Collection<int, Booking>  $qualifying
     * @param  Collection<int, UnitDateBlock>  $blocks
     * @return array<string, mixed>
     */
    private function buildOutlook(
        Carbon $outlookStart,
        Carbon $outlookEndDay,
        Collection $qualifying,
        Collection $blocks,
        int $totalUnits
    ): array {
        $days = [];
        $sumOcc = 0.0;
        $sumRevenue = 0.0;
        $dayCount = 14;

        for ($i = 0; $i < $dayCount; $i++) {
            $day = $outlookStart->copy()->addDays($i)->startOfDay();
            $bookedBlockedUnits = $this->countUnitsBookedOrBlocked($day, $qualifying, $blocks);
            $bookedBlockedPct = $totalUnits > 0
                ? round(($bookedBlockedUnits / $totalUnits) * 100, 2)
                : 0.0;
            $availabilityPct = round(max(0, min(100, 100 - $bookedBlockedPct)), 2);
            $revenue = round($this->revenueOnNight($day, $qualifying), 2);

            $sumOcc += $bookedBlockedPct;
            $sumRevenue += $revenue;

            $days[] = [
                'date' => $day->toDateString(),
                'weekdayShort' => strtoupper($day->format('D')),
                'dayOfMonth' => (int) $day->format('j'),
                'bookedBlockedPct' => $bookedBlockedPct,
                'availabilityPct' => $availabilityPct,
                'revenue' => $revenue,
            ];
        }

        $occupancyPct14d = $dayCount > 0 ? round($sumOcc / $dayCount, 2) : 0.0;
        $revenue14d = round($sumRevenue, 2);

        return [
            'startDate' => $outlookStart->toDateString(),
            'endDate' => $outlookEndDay->toDateString(),
            'occupancyPct14d' => $occupancyPct14d,
            'revenue14d' => $revenue14d,
            'days' => $days,
        ];
    }

    /**
     * @param  Collection<int, Booking>  $bookedToday
     * @return array<string, mixed>
     */
    private function buildSalesActivity(Collection $bookedToday): array
    {
        $roomNights = (int) $bookedToday->sum(fn (Booking $b) => MerchantBookingAnalytics::stayNights($b));
        $revenue = round((float) $bookedToday->sum(fn (Booking $b) => (float) $b->total_price), 2);

        return [
            'bookedTodayCount' => $bookedToday->count(),
            'roomNights' => $roomNights,
            'revenue' => $revenue,
            'rows' => $bookedToday->map(fn (Booking $b) => $this->salesRow($b))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salesRow(Booking $b): array
    {
        return [
            'bookingId' => $b->id,
            'guestName' => $b->guest_name,
            'revenue' => (float) $b->total_price,
            'currency' => $b->currency,
            'checkIn' => $b->check_in?->format('Y-m-d'),
            'nights' => MerchantBookingAnalytics::stayNights($b),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cancellationRow(Booking $b): array
    {
        return [
            'bookingId' => $b->id,
            'guestName' => $b->guest_name,
            'revenue' => (float) $b->total_price,
            'currency' => $b->currency,
            'checkIn' => $b->check_in?->format('Y-m-d'),
            'nights' => MerchantBookingAnalytics::stayNights($b),
            'updatedAt' => $b->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationRow(Booking $b, string $statusLabel): array
    {
        return [
            'bookingId' => $b->id,
            'guestName' => $b->guest_name,
            'guestPhone' => $b->guest_phone,
            'confirmation' => $b->reference,
            'room' => $this->roomLabel($b),
            'status' => $statusLabel,
        ];
    }

    private function newBookingsStatusLabel(Booking $b): string
    {
        return match ($b->status) {
            Booking::STATUS_PENDING => 'Pending',
            Booking::STATUS_ACCEPTED => 'Accepted',
            Booking::STATUS_ASSIGNED => 'Assigned',
            default => ucfirst((string) $b->status),
        };
    }

    /** Pending first, then Accepted, then Assigned; newest first within each group. */
    private function compareNewBookingsToday(Booking $a, Booking $b): int
    {
        $rank = fn (Booking $x): int => match ($x->status) {
            Booking::STATUS_PENDING => 0,
            Booking::STATUS_ACCEPTED => 1,
            Booking::STATUS_ASSIGNED => 2,
            default => 99,
        };

        $byStatus = $rank($a) <=> $rank($b);
        if ($byStatus !== 0) {
            return $byStatus;
        }

        $ta = $a->created_at?->getTimestamp() ?? 0;
        $tb = $b->created_at?->getTimestamp() ?? 0;

        return $tb <=> $ta;
    }

    private function roomLabel(Booking $b): string
    {
        $prop = $b->unit?->property?->property_name;
        $unit = $b->unit?->name;
        if ($prop && $unit) {
            return $prop.' · '.$unit;
        }

        return $unit ?? $prop ?? '—';
    }

    private function stayCoversDate(Booking $b, Carbon $day): bool
    {
        if ($b->status === Booking::STATUS_CANCELLED) {
            return false;
        }
        $in = Carbon::parse($b->check_in)->startOfDay();
        $out = Carbon::parse($b->check_out)->startOfDay();

        return $day->gte($in) && $day->lt($out);
    }

    private function isStayoverOn(Booking $b, Carbon $day): bool
    {
        if ($b->status === Booking::STATUS_CANCELLED) {
            return false;
        }
        $in = Carbon::parse($b->check_in)->startOfDay();
        $out = Carbon::parse($b->check_out)->startOfDay();

        return $day->gt($in) && $day->lt($out);
    }

    /**
     * @param  Collection<int, Booking>  $qualifying
     * @param  Collection<int, UnitDateBlock>  $blocks
     */
    private function countUnitsBookedOrBlocked(Carbon $night, Collection $qualifying, Collection $blocks): int
    {
        $bookedIds = $qualifying
            ->filter(fn (Booking $b) => $this->stayCoversDate($b, $night))
            ->pluck('unit_id')
            ->all();

        $blockedIds = $blocks
            ->filter(fn (UnitDateBlock $bl) => $this->blockCoversDate($bl, $night))
            ->pluck('unit_id')
            ->all();

        return count(array_unique(array_merge($bookedIds, $blockedIds)));
    }

    private function blockCoversDate(UnitDateBlock $bl, Carbon $day): bool
    {
        $start = Carbon::parse($bl->start_date)->startOfDay();
        $end = Carbon::parse($bl->end_date)->startOfDay();

        return $day->gte($start) && $day->lt($end);
    }

    /**
     * @param  Collection<int, Booking>  $qualifying
     */
    private function revenueOnNight(Carbon $night, Collection $qualifying): float
    {
        $sum = 0.0;
        foreach ($qualifying as $b) {
            if (! $this->stayCoversDate($b, $night)) {
                continue;
            }
            $nights = max(1, MerchantBookingAnalytics::stayNights($b));
            $sum += (float) $b->total_price / $nights;
        }

        return $sum;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findOverbookingRows(int $userId): array
    {
        $assigned = Booking::query()
            ->where('user_id', $userId)
            ->where('status', Booking::STATUS_ASSIGNED)
            ->with($this->bookingUnitEagerLoad())
            ->orderBy('unit_id')
            ->orderBy('check_in')
            ->get();

        $rows = [];
        $byUnit = $assigned->groupBy('unit_id');
        foreach ($byUnit as $list) {
            /** @var Collection<int, Booking> $list */
            $arr = $list->values()->all();
            $n = count($arr);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    if ($this->bookingsDateOverlap($arr[$i], $arr[$j])) {
                        $rows[] = [
                            'bookingIdA' => $arr[$i]->id,
                            'bookingIdB' => $arr[$j]->id,
                            'guestName' => $arr[$i]->guest_name.' / '.$arr[$j]->guest_name,
                            'room' => $this->roomLabel($arr[$i]),
                            'checkIn' => $arr[$i]->check_in?->format('Y-m-d'),
                            'checkOut' => $arr[$i]->check_out?->format('Y-m-d'),
                        ];
                    }
                }
            }
        }

        return array_slice($rows, 0, 40);
    }

    private function bookingsDateOverlap(Booking $a, Booking $b): bool
    {
        $ain = Carbon::parse($a->check_in)->startOfDay();
        $aout = Carbon::parse($a->check_out)->startOfDay();
        $bin = Carbon::parse($b->check_in)->startOfDay();
        $bout = Carbon::parse($b->check_out)->startOfDay();

        return $ain->lt($bout) && $bin->lt($aout);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingUnitEagerLoad(): array
    {
        return [
            'unit' => static function ($q): void {
                $q->select(
                    'id',
                    'property_id',
                    'name',
                    'type',
                    'max_guests',
                    'bedrooms',
                    'beds',
                    'price_per_night',
                    'currency',
                    'status'
                )->with([
                    'property' => static function ($q2): void {
                        $q2->select('id', 'property_name');
                    },
                ]);
            },
        ];
    }
}
