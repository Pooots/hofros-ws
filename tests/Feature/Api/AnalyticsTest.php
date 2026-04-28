<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Support\Carbon;

class AnalyticsTest extends ApiTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testSummaryReturnsDefaultStructure(): void
    {
        $this->authenticate();

        $this->getJson('/api/v1/analytics?year='.now()->year)
            ->assertOk()
            ->assertJsonStructure([
                'year',
                'currency',
                'kpis' => ['totalRevenue', 'totalBookings', 'avgOccupancy', 'adr'],
                'monthly',
                'sources',
                'occupancyByMonth',
                'units',
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSummaryValidatesYear(): void
    {
        $this->authenticate();
        $this->getJson('/api/v1/analytics?year=1900')
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSummaryAggregatesForUser(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);

        $year = (int) now()->year;
        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::createFromDate($year, 1, 5)->toDateString(),
            'check_out' => Carbon::createFromDate($year, 1, 8)->toDateString(),
            'total_price' => 3000,
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        $this->getJson('/api/v1/analytics?year='.$year)
            ->assertOk()
            ->assertJsonPath('kpis.totalRevenue.value', 3000)
            ->assertJsonPath('kpis.totalBookings.value', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testExportReturnsCsvStream(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);
        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::createFromDate((int) now()->year, 3, 1)->toDateString(),
            'check_out' => Carbon::createFromDate((int) now()->year, 3, 4)->toDateString(),
            'total_price' => 4500,
            'status' => Booking::STATUS_ACCEPTED,
            'adults' => 1,
            'children' => 0,
        ]);

        $response = $this->get('/api/v1/analytics/export?year='.now()->year.'&granularity=monthly');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('analytics-bookings-', (string) $response->headers->get('content-disposition'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('period,period_start,period_end,bookings_count', $body);
        $this->assertStringContainsString('PHP', $body);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testExportSupportsAllGranularities(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);
        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::createFromDate((int) now()->year, 5, 5)->toDateString(),
            'check_out' => Carbon::createFromDate((int) now()->year, 5, 8)->toDateString(),
            'total_price' => 3000,
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        foreach (['yearly', 'monthly', 'weekly', 'daily'] as $granularity) {
            $response = $this->get('/api/v1/analytics/export?year='.now()->year.'&granularity='.$granularity);
            $response->assertOk();
            $body = $response->streamedContent();
            $this->assertStringContainsString('period', $body);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testExportValidatesPayload(): void
    {
        $this->authenticate();
        $this->getJson('/api/v1/analytics/export?year=2025&granularity=quarterly')
            ->assertStatus(422);
    }
}
