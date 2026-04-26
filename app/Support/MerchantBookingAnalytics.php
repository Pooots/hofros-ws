<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class MerchantBookingAnalytics
{
    public const CURRENCY_CODE = 'PHP';

    public const CURRENCY_SYMBOL = '₱';

    /** @return Builder<Booking> */
    public static function qualifyingBookingsQuery(int $userId): Builder
    {
        return Booking::query()
            ->where('user_id', $userId)
            ->where('status', '!=', Booking::STATUS_CANCELLED);
    }

    public static function activeUnitsCount(int $userId): int
    {
        return (int) Unit::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->count();
    }

    /**
     * Bookings with check-in falling in the calendar year (revenue & booking counts).
     *
     * @return Collection<int, Booking>
     */
    public static function bookingsForYear(int $userId, int $year): Collection
    {
        $start = "{$year}-01-01";
        $end = "{$year}-12-31";

        return self::qualifyingBookingsQuery($userId)
            ->whereDate('check_in', '>=', $start)
            ->whereDate('check_in', '<=', $end)
            ->get();
    }

    /**
     * Bookings whose stay overlaps the calendar year (occupancy / night-based stats).
     *
     * @return Collection<int, Booking>
     */
    public static function bookingsOverlappingYear(int $userId, int $year): Collection
    {
        return self::qualifyingBookingsQuery($userId)
            ->whereDate('check_in', '<=', "{$year}-12-31")
            ->whereDate('check_out', '>', "{$year}-01-01")
            ->get();
    }

    public static function totalRevenue(Collection $bookings): float
    {
        return round((float) $bookings->sum(fn (Booking $b) => (float) $b->total_price), 2);
    }

    public static function totalBookedNights(Collection $bookings): int
    {
        return (int) $bookings->sum(fn (Booking $b) => self::stayNights($b));
    }

    public static function stayNights(Booking $b): int
    {
        $in = Carbon::parse($b->check_in)->startOfDay();
        $out = Carbon::parse($b->check_out)->startOfDay();

        return max(0, (int) $in->diffInDays($out));
    }

    public static function nightsInCalendarMonth(Booking $b, int $year, int $month): int
    {
        $in = Carbon::parse($b->check_in)->startOfDay();
        $out = Carbon::parse($b->check_out)->startOfDay();
        $n = 0;
        for ($d = $in->copy(); $d->lt($out); $d->addDay()) {
            if ($d->year === $year && (int) $d->month === $month) {
                $n++;
            }
        }

        return $n;
    }

    public static function nightsInCalendarYear(Booking $b, int $year): int
    {
        $in = Carbon::parse($b->check_in)->startOfDay();
        $out = Carbon::parse($b->check_out)->startOfDay();
        $yearStart = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::createFromDate($year, 12, 31)->endOfDay();
        $n = 0;
        for ($d = $in->copy(); $d->lt($out); $d->addDay()) {
            if ($d->gte($yearStart) && $d->lte($yearEnd)) {
                $n++;
            }
        }

        return $n;
    }

    public static function occupancyPctForMonth(int $userId, int $year, int $month, Collection $bookingsOverlapping): float
    {
        $units = self::activeUnitsCount($userId);
        if ($units === 0) {
            return 0.0;
        }
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $denominator = $units * $daysInMonth;
        $numerator = 0;
        foreach ($bookingsOverlapping as $b) {
            $numerator += self::nightsInCalendarMonth($b, $year, $month);
        }
        if ($denominator === 0) {
            return 0.0;
        }

        return round(min(100.0, ($numerator / $denominator) * 100), 1);
    }

    public static function yearAvgOccupancy(int $userId, int $year, Collection $bookingsOverlapping): float
    {
        $sum = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $sum += self::occupancyPctForMonth($userId, $year, $m, $bookingsOverlapping);
        }

        return round($sum / 12, 1);
    }

    /**
     * @return array{key: string, label: string}
     */
    public static function mapSourceKeyLabel(string $source): array
    {
        $s = strtolower(trim($source));
        $map = [
            'direct_portal' => ['key' => 'direct', 'label' => 'Direct'],
            'manual' => ['key' => 'manual', 'label' => 'Manual'],
            'airbnb' => ['key' => 'airbnb', 'label' => 'Airbnb'],
            'booking_com' => ['key' => 'booking_com', 'label' => 'Booking.com'],
            'booking.com' => ['key' => 'booking_com', 'label' => 'Booking.com'],
            'expedia' => ['key' => 'expedia', 'label' => 'Expedia'],
            'vrbo' => ['key' => 'vrbo', 'label' => 'VRBO'],
        ];
        if (isset($map[$s])) {
            return $map[$s];
        }

        $label = $s !== '' ? ucwords(str_replace('_', ' ', $s)) : 'Other';

        return ['key' => $s !== '' ? $s : 'other', 'label' => $label];
    }

    public static function pctChange(float $current, float $previous): float
    {
        if ($previous <= 0.0) {
            return $current > 0.0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return list<array{period: string, period_start: string, period_end: string, bookings_count: int, total_revenue: float, guest_nights: int}>
     */
    public static function exportRows(int $userId, int $year, string $granularity): array
    {
        $bookings = self::qualifyingBookingsQuery($userId)
            ->whereDate('check_in', '>=', "{$year}-01-01")
            ->whereDate('check_in', '<=', "{$year}-12-31")
            ->orderBy('check_in')
            ->get();

        return match ($granularity) {
            'daily' => self::aggregateDaily($bookings, $year),
            'weekly' => self::aggregateWeekly($bookings, $year),
            'monthly' => self::aggregateMonthly($bookings, $year),
            'yearly' => self::aggregateYearly($bookings, $year),
            default => [],
        };
    }

    /**
     * @param Collection<int, Booking> $bookings
     * @return list<array{period: string, period_start: string, period_end: string, bookings_count: int, total_revenue: float, guest_nights: int}>
     */
    private static function aggregateDaily(Collection $bookings, int $year): array
    {
        $byDay = $bookings->groupBy(fn (Booking $b) => Carbon::parse($b->check_in)->format('Y-m-d'));
        $rows = [];
        $cursor = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $end = Carbon::createFromDate($year, 12, 31)->startOfDay();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d');
            /** @var Collection<int, Booking> $dayBookings */
            $dayBookings = $byDay->get($key, collect());
            $rows[] = self::rowFromBookings($key, $key, $key, $dayBookings);
            $cursor->addDay();
        }

        return $rows;
    }

    /**
     * @param Collection<int, Booking> $bookings
     * @return list<array{period: string, period_start: string, period_end: string, bookings_count: int, total_revenue: float, guest_nights: int}>
     */
    private static function aggregateWeekly(Collection $bookings, int $year): array
    {
        $byWeek = $bookings->groupBy(function (Booking $b): string {
            return Carbon::parse($b->check_in)->startOfDay()->isoFormat('GGGG-[W]WW');
        });
        $weekMeta = [];
        $day = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $last = Carbon::createFromDate($year, 12, 31)->startOfDay();
        while ($day->lte($last)) {
            $key = $day->isoFormat('GGGG-[W]WW');
            if (! isset($weekMeta[$key])) {
                $mon = $day->copy()->startOfWeek(Carbon::MONDAY);
                $sun = $day->copy()->endOfWeek(Carbon::SUNDAY);
                $weekMeta[$key] = [$mon->toDateString(), $sun->toDateString()];
            }
            $day->addDay();
        }
        ksort($weekMeta);
        $rows = [];
        foreach ($weekMeta as $key => [$start, $end]) {
            /** @var Collection<int, Booking> $wkBookings */
            $wkBookings = $byWeek->get($key, collect());
            $rows[] = self::rowFromBookings($key, $start, $end, $wkBookings);
        }

        return $rows;
    }

    /**
     * @param Collection<int, Booking> $bookings
     * @return list<array{period: string, period_start: string, period_end: string, bookings_count: int, total_revenue: float, guest_nights: int}>
     */
    private static function aggregateMonthly(Collection $bookings, int $year): array
    {
        $byMonth = $bookings->groupBy(fn (Booking $b) => (int) Carbon::parse($b->check_in)->month);
        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $start = Carbon::createFromDate($year, $m, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            /** @var Collection<int, Booking> $monthBookings */
            $monthBookings = $byMonth->get($m, collect());
            $rows[] = self::rowFromBookings(
                sprintf('%04d-%02d', $year, $m),
                $start->toDateString(),
                $end->toDateString(),
                $monthBookings
            );
        }

        return $rows;
    }

    /**
     * @param Collection<int, Booking> $bookings
     * @return list<array{period: string, period_start: string, period_end: string, bookings_count: int, total_revenue: float, guest_nights: int}>
     */
    private static function aggregateYearly(Collection $bookings, int $year): array
    {
        $inYear = $bookings->filter(function (Booking $b) use ($year): bool {
            return Carbon::parse($b->check_in)->year === $year;
        });

        return [
            self::rowFromBookings(
                (string) $year,
                "{$year}-01-01",
                "{$year}-12-31",
                $inYear
            ),
        ];
    }

    /**
     * @param Collection<int, Booking> $group
     * @return array{period: string, period_start: string, period_end: string, bookings_count: int, total_revenue: float, guest_nights: int}
     */
    private static function rowFromBookings(
        string $period,
        string $periodStart,
        string $periodEnd,
        Collection $group
    ): array {
        return [
            'period' => $period,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'bookings_count' => $group->count(),
            'total_revenue' => self::totalRevenue($group),
            'guest_nights' => self::totalBookedNights($group),
        ];
    }
}
