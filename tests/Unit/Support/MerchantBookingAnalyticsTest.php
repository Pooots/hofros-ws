<?php

namespace Tests\Unit\Support;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Support\MerchantBookingAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MerchantBookingAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(array $overrides = []): Booking
    {
        $owner = $overrides['user'] ?? null;
        $owner = $owner ?: User::factory()->create();
        unset($overrides['user']);
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'status' => Unit::STATUS_ACTIVE,
        ]);

        return Booking::factory()->create(array_merge([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => '2025-06-01',
            'check_out' => '2025-06-04',
            'total_price' => 4500,
            'status' => Booking::STATUS_ACCEPTED,
            'source' => Booking::SOURCE_MANUAL,
            'adults' => 1,
            'children' => 0,
        ], $overrides));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testQualifyingBookingsQueryExcludesCancelled(): void
    {
        $owner = User::factory()->create();
        $this->makeBooking(['user' => $owner, 'status' => Booking::STATUS_ACCEPTED]);
        $this->makeBooking(['user' => $owner, 'status' => Booking::STATUS_CANCELLED]);

        $rows = MerchantBookingAnalytics::qualifyingBookingsQuery($owner->uuid)->get();
        $this->assertCount(1, $rows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testActiveUnitsCountFiltersByStatus(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid, 'status' => Unit::STATUS_ACTIVE]);
        Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid, 'status' => Unit::STATUS_ACTIVE]);
        Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid, 'status' => 'inactive']);

        $this->assertSame(2, MerchantBookingAnalytics::activeUnitsCount($owner->uuid));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testTotalRevenueAndNightsAggregate(): void
    {
        $owner = User::factory()->create();
        $b1 = $this->makeBooking(['user' => $owner, 'check_in' => '2025-01-01', 'check_out' => '2025-01-04', 'total_price' => 1500]);
        $b2 = $this->makeBooking(['user' => $owner, 'check_in' => '2025-02-10', 'check_out' => '2025-02-12', 'total_price' => 2000]);
        $bookings = collect([$b1, $b2]);

        $this->assertSame(3500.0, MerchantBookingAnalytics::totalRevenue($bookings));
        $this->assertSame(5, MerchantBookingAnalytics::totalBookedNights($bookings));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testNightsInCalendarMonthAndYear(): void
    {
        $owner = User::factory()->create();
        $booking = $this->makeBooking([
            'user' => $owner,
            'check_in' => '2025-12-30',
            'check_out' => '2026-01-03',
        ]);

        $this->assertSame(2, MerchantBookingAnalytics::nightsInCalendarMonth($booking, 2025, 12));
        $this->assertSame(2, MerchantBookingAnalytics::nightsInCalendarMonth($booking, 2026, 1));
        $this->assertSame(2, MerchantBookingAnalytics::nightsInCalendarYear($booking, 2025));
        $this->assertSame(2, MerchantBookingAnalytics::nightsInCalendarYear($booking, 2026));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testOccupancyZeroWhenNoActiveUnits(): void
    {
        $owner = User::factory()->create();
        $this->assertSame(0.0, MerchantBookingAnalytics::occupancyPctForMonth($owner->uuid, 2025, 6, collect()));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testOccupancyCapsAt100(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'status' => Unit::STATUS_ACTIVE,
        ]);
        $b = Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => '2025-06-01',
            'check_out' => '2025-07-01',
            'status' => Booking::STATUS_ACCEPTED,
            'total_price' => 30000,
            'adults' => 1,
            'children' => 0,
        ]);

        $occ = MerchantBookingAnalytics::occupancyPctForMonth($owner->uuid, 2025, 6, collect([$b]));
        $this->assertSame(100.0, $occ);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testYearAvgOccupancyAggregatesMonths(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'status' => Unit::STATUS_ACTIVE,
        ]);

        $avg = MerchantBookingAnalytics::yearAvgOccupancy($owner->uuid, 2025, collect());
        $this->assertSame(0.0, $avg);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testExportRowsYearlyReturnsSummary(): void
    {
        $owner = User::factory()->create();
        $this->makeBooking(['user' => $owner, 'check_in' => '2025-01-05', 'check_out' => '2025-01-08', 'total_price' => 3000]);
        $this->makeBooking(['user' => $owner, 'check_in' => '2025-06-12', 'check_out' => '2025-06-15', 'total_price' => 4500]);

        $rows = MerchantBookingAnalytics::exportRows($owner->uuid, 2025, 'yearly');
        $this->assertCount(1, $rows);
        $this->assertSame('2025', $rows[0]['period']);
        $this->assertSame(2, $rows[0]['bookings_count']);
        $this->assertSame(7500.0, $rows[0]['total_revenue']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testExportRowsMonthlyReturns12Rows(): void
    {
        $owner = User::factory()->create();
        $this->makeBooking(['user' => $owner, 'check_in' => '2025-03-01', 'check_out' => '2025-03-03', 'total_price' => 2000]);

        $rows = MerchantBookingAnalytics::exportRows($owner->uuid, 2025, 'monthly');
        $this->assertCount(12, $rows);
        $this->assertSame('2025-03', $rows[2]['period']);
        $this->assertSame(1, $rows[2]['bookings_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testExportRowsDailyReturnsFullYear(): void
    {
        $owner = User::factory()->create();
        $rows = MerchantBookingAnalytics::exportRows($owner->uuid, 2025, 'daily');
        $this->assertSame(365, count($rows));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testExportRowsWeeklyReturnsRows(): void
    {
        $owner = User::factory()->create();
        $rows = MerchantBookingAnalytics::exportRows($owner->uuid, 2025, 'weekly');
        $this->assertGreaterThanOrEqual(52, count($rows));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testExportRowsInvalidGranularityReturnsEmpty(): void
    {
        $owner = User::factory()->create();
        $rows = MerchantBookingAnalytics::exportRows($owner->uuid, 2025, 'quarterly');
        $this->assertSame([], $rows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStayNightsAndBookingsForYear(): void
    {
        $owner = User::factory()->create();
        $b = $this->makeBooking(['user' => $owner, 'check_in' => '2025-06-01', 'check_out' => '2025-06-05']);
        $this->assertSame(4, MerchantBookingAnalytics::stayNights($b));

        $bookings = MerchantBookingAnalytics::bookingsForYear($owner->uuid, 2025);
        $this->assertCount(1, $bookings);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookingsOverlappingYear(): void
    {
        $owner = User::factory()->create();
        $this->makeBooking(['user' => $owner, 'check_in' => '2024-12-30', 'check_out' => '2025-01-04']);

        $rows = MerchantBookingAnalytics::bookingsOverlappingYear($owner->uuid, 2025);
        $this->assertCount(1, $rows);
    }
}
