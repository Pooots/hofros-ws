<?php

namespace App\Support;

use App\Models\Unit;
use App\Models\UnitRateInterval;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves nightly rates from {@see UnitRateInterval} rows (Availability / Base Rates in Configuration)
 * with fallback to {@see Unit::$price_per_night}. Used by the direct booking portal and merchant bookings.
 */
final class UnitStayPricing
{
    /** @var list<string> */
    public const DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public static function weekdayKey(CarbonInterface $d): string
    {
        $w = (int) $d->format('w');

        return self::DAY_KEYS[$w];
    }

    /**
     * Lowest nightly amount for portal listing from today forward.
     *
     * @see displayMinMaxNightlyForPortal
     */
    public static function displayMinNightlyForPortal(Unit $unit): float
    {
        return self::displayMinMaxNightlyForPortal($unit)['min'];
    }

    /**
     * Min and max nightly amounts for portal listing cards (from today forward).
     *
     * Rule (same as legacy "From" logic):
     * - If at least one future/current interval day is configured, use interval
     *   prices only (Configuration > Rates); min/max across all enabled weekdays and overrides.
     * - Otherwise, both min and max are unit.price_per_night.
     *
     * @return array{min: float, max: float}
     */
    public static function displayMinMaxNightlyForPortal(Unit $unit): array
    {
        $candidates = self::portalListingNightlyPriceCandidates($unit);
        $min = min($candidates);
        $max = max($candidates);

        return [
            'min' => round((float) $min, 2),
            'max' => round((float) $max, 2),
        ];
    }

    /**
     * Nightly price points for portal listing display (non-empty).
     *
     * @return list<float>
     */
    private static function portalListingNightlyPriceCandidates(Unit $unit): array
    {
        $today = now()->startOfDay();
        $legacy = round((float) $unit->price_per_night, 2);
        $intervalCandidates = [];

        $intervals = $unit->relationLoaded('rateIntervals')
            ? $unit->rateIntervals
            : $unit->rateIntervals()->orderBy('start_date')->orderBy('id')->get();

        foreach ($intervals as $interval) {
            if ($interval->start_date === null || $interval->end_date === null) {
                continue;
            }
            if ($interval->end_date->lt($today)) {
                continue;
            }

            $days = self::normalizeDaysOfWeek(is_array($interval->days_of_week) ? $interval->days_of_week : []);
            if (! in_array(true, $days, true)) {
                continue;
            }

            $prices = is_array($interval->day_prices) ? $interval->day_prices : [];
            $base = round((float) $interval->base_price, 2);
            foreach (self::DAY_KEYS as $k) {
                if (empty($days[$k])) {
                    continue;
                }

                $v = $base;
                if (array_key_exists($k, $prices) && is_numeric($prices[$k])) {
                    $v = round((float) $prices[$k], 2);
                }

                $intervalCandidates[] = $v;
            }
        }

        if ($intervalCandidates === []) {
            return [round(max(0, $legacy), 2)];
        }

        return array_values(array_map(static fn (float $v): float => round(max(0, $v), 2), $intervalCandidates));
    }

    /**
     * @return array{total: float, currency: string, nights: int, error: string|null}
     */
    public static function computeForStay(Unit $unit, CarbonInterface $checkIn, CarbonInterface $checkOut): array
    {
        $checkIn = $checkIn->copy()->startOfDay();
        $checkOut = $checkOut->copy()->startOfDay();
        $nights = max(1, $checkIn->diffInDays($checkOut));

        $intervals = $unit->rateIntervals()->orderBy('start_date')->orderBy('id')->get();

        $err = self::validationError($unit, $checkIn, $checkOut, $nights, $intervals);
        if ($err !== null) {
            return [
                'total' => 0.0,
                'currency' => (string) $unit->currency,
                'nights' => $nights,
                'error' => $err,
            ];
        }

        $total = 0.0;
        $night = $checkIn->copy();
        while ($night->lt($checkOut)) {
            $total += self::nightlyPrice($unit, $night, $intervals);
            $night->addDay();
        }

        return [
            'total' => round($total, 2),
            'currency' => (string) $unit->currency,
            'nights' => $nights,
            'error' => null,
        ];
    }

    /**
     * @param  Collection<int, UnitRateInterval>  $intervals
     */
    public static function validationError(
        Unit $unit,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        int $nights,
        Collection $intervals,
    ): ?string {
        $first = self::bestIntervalForNight($intervals, $checkIn->copy()->startOfDay());
        if ($first !== null) {
            if ($first->closed_to_arrival) {
                return 'Arrival is not allowed on the selected check-in date for this rate.';
            }
            if ($first->min_los !== null && $nights < (int) $first->min_los) {
                return 'Minimum stay for these dates is '.$first->min_los.' night(s).';
            }
            if ($first->max_los !== null && $nights > (int) $first->max_los) {
                return 'Maximum stay for these dates is '.$first->max_los.' night(s).';
            }
        }

        $lastNight = $checkOut->copy()->subDay()->startOfDay();
        if ($lastNight->gte($checkIn->copy()->startOfDay())) {
            $last = self::bestIntervalForNight($intervals, $lastNight);
            if ($last !== null && $last->closed_to_departure) {
                return 'Departure is not allowed on the selected check-out date for this rate.';
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, UnitRateInterval>  $intervals
     */
    private static function nightlyPrice(Unit $unit, CarbonInterface $night, Collection $intervals): float
    {
        $best = self::bestIntervalForNight($intervals, $night->copy()->startOfDay());
        if ($best === null) {
            return round((float) $unit->price_per_night, 2);
        }

        return self::dayPriceFromInterval($best, self::weekdayKey($night));
    }

    private static function dayPriceFromInterval(UnitRateInterval $interval, string $weekKey): float
    {
        $days = self::normalizeDaysOfWeek(is_array($interval->days_of_week) ? $interval->days_of_week : []);
        $raw = is_array($interval->day_prices) ? $interval->day_prices : [];
        if (array_key_exists($weekKey, $raw) && is_numeric($raw[$weekKey])) {
            return round((float) $raw[$weekKey], 2);
        }

        return round((float) $interval->base_price, 2);
    }

    /**
     * @param  Collection<int, UnitRateInterval>  $intervals
     */
    private static function bestIntervalForNight(Collection $intervals, CarbonInterface $night): ?UnitRateInterval
    {
        $nightYmd = $night->toDateString();
        $wk = self::weekdayKey($night);

        $candidates = $intervals->filter(static function (UnitRateInterval $i) use ($nightYmd, $wk): bool {
            $start = $i->start_date?->toDateString();
            $end = $i->end_date?->toDateString();
            if ($start === null || $end === null) {
                return false;
            }
            if ($nightYmd < $start || $nightYmd > $end) {
                return false;
            }
            $days = self::normalizeDaysOfWeek(is_array($i->days_of_week) ? $i->days_of_week : []);

            return ! empty($days[$wk]);
        })->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        $sorted = $candidates->sort(static function (UnitRateInterval $a, UnitRateInterval $b): int {
            $spanA = $a->start_date->diffInDays($a->end_date);
            $spanB = $b->start_date->diffInDays($b->end_date);
            if ($spanA !== $spanB) {
                return $spanA <=> $spanB;
            }

            return $b->id <=> $a->id;
        });

        return $sorted->first();
    }

    /** @param  array<string, mixed>|null  $raw */
    private static function normalizeDaysOfWeek(?array $raw): array
    {
        $out = [];
        foreach (self::DAY_KEYS as $key) {
            $out[$key] = ! empty($raw[$key]);
        }

        return $out;
    }
}
