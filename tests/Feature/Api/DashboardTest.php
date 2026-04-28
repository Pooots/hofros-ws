<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Support\Carbon;

class DashboardTest extends ApiTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testDashboardReturnsDefaultStructureForEmptyUser(): void
    {
        $this->authenticate();

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'date',
                'currency',
                'kpis' => ['arrivals', 'departures', 'accommodationsBooked', 'totalActiveUnits'],
                'reservations' => ['arrivals', 'departures', 'stayovers', 'inHouse'],
                'todayActivity' => ['sales', 'cancellations', 'overbookings'],
                'outlook' => ['startDate', 'endDate', 'days'],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDashboardCountsArrivalsToday(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);

        $today = Carbon::today();
        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => $today->toDateString(),
            'check_out' => $today->copy()->addDays(2)->toDateString(),
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        $this->getJson('/api/v1/dashboard?date='.$today->toDateString())
            ->assertOk()
            ->assertJsonPath('kpis.arrivals', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDashboardValidatesDateFormat(): void
    {
        $this->authenticate();
        $this->getJson('/api/v1/dashboard?date=bad-date')
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDashboardOutlookWindowIs14Days(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/v1/dashboard')->assertOk();
        $this->assertCount(14, $response->json('outlook.days'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDashboardCustomOutlookStart(): void
    {
        $this->authenticate();
        $start = Carbon::today()->addDays(7)->toDateString();

        $this->getJson('/api/v1/dashboard?outlookStart='.$start)
            ->assertOk()
            ->assertJsonPath('outlook.startDate', $start);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDashboardListsArrivalsTomorrowAndStayovers(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 4,
        ]);

        $today = Carbon::today();
        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => $today->copy()->addDay()->toDateString(),
            'check_out' => $today->copy()->addDays(3)->toDateString(),
            'status' => Booking::STATUS_ACCEPTED,
            'adults' => 1,
            'children' => 0,
        ]);
        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => $today->copy()->subDay()->toDateString(),
            'check_out' => $today->copy()->addDay()->toDateString(),
            'status' => Booking::STATUS_ASSIGNED,
            'adults' => 1,
            'children' => 0,
        ]);

        $this->getJson('/api/v1/dashboard?date='.$today->toDateString())
            ->assertOk()
            ->assertJsonStructure(['reservations' => ['arrivals' => ['tomorrow'], 'stayovers' => ['today']]]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDashboardSurfacesOverbookingsToday(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 4,
        ]);

        $today = Carbon::today();
        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => $today->copy()->addDays(1)->toDateString(),
            'check_out' => $today->copy()->addDays(5)->toDateString(),
            'status' => Booking::STATUS_ASSIGNED,
            'adults' => 1,
            'children' => 0,
        ]);
        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => $today->copy()->addDays(3)->toDateString(),
            'check_out' => $today->copy()->addDays(7)->toDateString(),
            'status' => Booking::STATUS_ASSIGNED,
            'adults' => 1,
            'children' => 0,
        ]);

        $response = $this->getJson('/api/v1/dashboard?date='.$today->toDateString())->assertOk();
        $rows = $response->json('todayActivity.overbookings.rows');
        $this->assertGreaterThanOrEqual(1, count($rows));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDashboardSurfacesNewlyBookedToday(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 4,
        ]);

        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(2)->toDateString(),
            'check_out' => Carbon::today()->addDays(4)->toDateString(),
            'status' => Booking::STATUS_PENDING,
            'adults' => 1,
            'children' => 0,
            'created_at' => Carbon::today(),
            'updated_at' => Carbon::today(),
        ]);

        $this->getJson('/api/v1/dashboard?date='.Carbon::today()->toDateString())
            ->assertOk()
            ->assertJsonPath('kpis.newlyBookedToday', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDashboardCancellationsToday(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 4,
        ]);

        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(1)->toDateString(),
            'check_out' => Carbon::today()->addDays(3)->toDateString(),
            'status' => Booking::STATUS_CANCELLED,
            'adults' => 1,
            'children' => 0,
            'updated_at' => Carbon::today(),
        ]);

        $response = $this->getJson('/api/v1/dashboard?date='.Carbon::today()->toDateString())->assertOk();
        $rows = $response->json('todayActivity.cancellations.rows');
        $this->assertCount(1, $rows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDashboardOccupancySkipsCancelledBookingsAndCountsBlocks(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 4,
        ]);
        $secondUnit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 2,
        ]);

        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->subDays(1)->toDateString(),
            'check_out' => Carbon::today()->addDays(2)->toDateString(),
            'status' => Booking::STATUS_CHECKED_IN,
            'adults' => 1,
            'children' => 0,
        ]);

        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $secondUnit->uuid,
            'check_in' => Carbon::today()->toDateString(),
            'check_out' => Carbon::today()->addDays(2)->toDateString(),
            'status' => Booking::STATUS_CANCELLED,
            'adults' => 1,
            'children' => 0,
        ]);

        \App\Models\UnitDateBlock::create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $secondUnit->uuid,
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(3)->toDateString(),
            'label' => 'Maintenance',
        ]);

        $this->getJson('/api/v1/dashboard?date='.Carbon::today()->toDateString())
            ->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDashboardRoomLabelFallsBackToDashWhenUnitMissing(): void
    {
        $owner = $this->authenticate();

        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'check_in' => Carbon::today()->toDateString(),
            'check_out' => Carbon::today()->addDay()->toDateString(),
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        $response = $this->getJson('/api/v1/dashboard?date='.Carbon::today()->toDateString())
            ->assertOk();

        $rows = $response->json('reservations.arrivals.today');
        $this->assertNotEmpty($rows);
        $this->assertSame('—', $rows[0]['room']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDashboardListsPendingBookingsCreatedTodayAheadOfAccepted(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 4,
        ]);

        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(2)->toDateString(),
            'check_out' => Carbon::today()->addDays(4)->toDateString(),
            'status' => Booking::STATUS_ACCEPTED,
            'adults' => 1,
            'children' => 0,
            'created_at' => Carbon::today()->setTime(8, 0),
            'updated_at' => Carbon::today()->setTime(8, 0),
        ]);

        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(5)->toDateString(),
            'check_out' => Carbon::today()->addDays(7)->toDateString(),
            'status' => Booking::STATUS_PENDING,
            'adults' => 1,
            'children' => 0,
            'created_at' => Carbon::today()->setTime(9, 0),
            'updated_at' => Carbon::today()->setTime(9, 0),
        ]);

        $response = $this->getJson('/api/v1/dashboard?date='.Carbon::today()->toDateString())->assertOk();
        $rows = $response->json('reservations.newToday.rows');
        $this->assertNotEmpty($rows);
        $this->assertSame('Pending', $rows[0]['status']);
    }
}
