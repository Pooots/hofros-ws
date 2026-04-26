<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Unit;
use App\Support\MerchantBookingAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);
        $year = $validated['year'] ?? (int) now()->year;
        $userId = (int) $request->user()->id;

        $bookings = MerchantBookingAnalytics::bookingsForYear($userId, $year);
        $prevYearBookings = MerchantBookingAnalytics::bookingsForYear($userId, $year - 1);
        $overlap = MerchantBookingAnalytics::bookingsOverlappingYear($userId, $year);
        $prevOverlap = MerchantBookingAnalytics::bookingsOverlappingYear($userId, $year - 1);

        $revenue = MerchantBookingAnalytics::totalRevenue($bookings);
        $prevRevenue = MerchantBookingAnalytics::totalRevenue($prevYearBookings);
        $bookingCount = $bookings->count();
        $prevBookingCount = $prevYearBookings->count();
        $nights = MerchantBookingAnalytics::totalBookedNights($bookings);
        $prevNights = MerchantBookingAnalytics::totalBookedNights($prevYearBookings);

        $avgOcc = MerchantBookingAnalytics::yearAvgOccupancy($userId, $year, $overlap);
        $prevAvgOcc = MerchantBookingAnalytics::yearAvgOccupancy($userId, $year - 1, $prevOverlap);

        $adr = $nights > 0 ? round($revenue / $nights, 2) : 0.0;
        $prevAdr = $prevNights > 0 ? round($prevRevenue / $prevNights, 2) : 0.0;

        $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $byMonth = $bookings->groupBy(fn (Booking $b) => (int) Carbon::parse($b->check_in)->month);
        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            /** @var \Illuminate\Support\Collection<int, Booking> $monthBookings */
            $monthBookings = $byMonth->get($m, collect());
            $monthly[] = [
                'month' => $m,
                'label' => $monthLabels[$m - 1],
                'revenue' => MerchantBookingAnalytics::totalRevenue($monthBookings),
                'bookings' => $monthBookings->count(),
            ];
        }

        $occupancyByMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $occupancyByMonth[] = [
                'month' => $m,
                'label' => $monthLabels[$m - 1],
                'occupancyPct' => MerchantBookingAnalytics::occupancyPctForMonth($userId, $year, $m, $overlap),
            ];
        }

        $sourceBuckets = [];
        foreach ($bookings as $b) {
            $mapped = MerchantBookingAnalytics::mapSourceKeyLabel((string) $b->source);
            $key = $mapped['key'];
            if (! isset($sourceBuckets[$key])) {
                $sourceBuckets[$key] = ['label' => $mapped['label'], 'count' => 0];
            }
            $sourceBuckets[$key]['count']++;
        }
        $totalForPct = max(1, $bookingCount);
        $sources = [];
        foreach ($sourceBuckets as $key => $row) {
            $sources[] = [
                'key' => $key,
                'label' => $row['label'],
                'count' => $row['count'],
                'pct' => round(($row['count'] / $totalForPct) * 100, 1),
            ];
        }
        usort($sources, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        $daysInYear = Carbon::createFromDate($year, 1, 1)->isLeapYear() ? 366 : 365;
        $unitsOut = [];
        $units = Unit::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach ($units as $unit) {
            $uBookings = $bookings->where('unit_id', $unit->id);
            $uRev = MerchantBookingAnalytics::totalRevenue($uBookings);
            $uBk = $uBookings->count();
            $uNightsYear = 0;
            foreach ($overlap->where('unit_id', $unit->id) as $ob) {
                $uNightsYear += MerchantBookingAnalytics::nightsInCalendarYear($ob, $year);
            }
            $uOcc = $daysInYear > 0 ? round(min(100.0, ($uNightsYear / $daysInYear) * 100), 1) : 0.0;
            $uNightsAdr = MerchantBookingAnalytics::totalBookedNights($uBookings);
            $uAdr = $uNightsAdr > 0 ? round($uRev / $uNightsAdr, 2) : 0.0;
            $unitsOut[] = [
                'unitId' => $unit->id,
                'name' => $unit->name,
                'totalRevenue' => $uRev,
                'bookings' => $uBk,
                'occupancyPct' => $uOcc,
                'adr' => $uAdr,
            ];
        }

        return response()->json([
            'year' => $year,
            'currency' => MerchantBookingAnalytics::CURRENCY_CODE,
            'currencySymbol' => MerchantBookingAnalytics::CURRENCY_SYMBOL,
            'kpis' => [
                'totalRevenue' => [
                    'value' => $revenue,
                    'changePct' => MerchantBookingAnalytics::pctChange($revenue, $prevRevenue),
                ],
                'totalBookings' => [
                    'value' => $bookingCount,
                    'changePct' => MerchantBookingAnalytics::pctChange((float) $bookingCount, (float) $prevBookingCount),
                ],
                'avgOccupancy' => [
                    'value' => $avgOcc,
                    'changePct' => MerchantBookingAnalytics::pctChange($avgOcc, $prevAvgOcc),
                ],
                'adr' => [
                    'value' => $adr,
                    'changePct' => MerchantBookingAnalytics::pctChange($adr, $prevAdr),
                ],
            ],
            'monthly' => $monthly,
            'sources' => $sources,
            'occupancyByMonth' => $occupancyByMonth,
            'units' => $unitsOut,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'granularity' => ['required', 'string', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
        ]);
        $year = (int) $validated['year'];
        $granularity = (string) $validated['granularity'];
        $userId = (int) $request->user()->id;

        $rows = MerchantBookingAnalytics::exportRows($userId, $year, $granularity);
        $safeGran = preg_replace('/[^a-z]/', '', $granularity) ?? $granularity;
        $filename = 'analytics-bookings-'.$year.'-'.$safeGran.'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'period',
                'period_start',
                'period_end',
                'bookings_count',
                'total_revenue_php',
                'guest_nights',
                'currency',
            ]);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['period'],
                    $r['period_start'],
                    $r['period_end'],
                    $r['bookings_count'],
                    $r['total_revenue'],
                    $r['guest_nights'],
                    MerchantBookingAnalytics::CURRENCY_CODE,
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
