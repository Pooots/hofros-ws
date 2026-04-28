<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use Illuminate\Support\Carbon;

class CalendarTest extends ApiTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testCalendarRequiresDateRange(): void
    {
        $this->authenticate();
        $this->getJson('/api/v1/calendar')->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCalendarRejectsInvalidRange(): void
    {
        $this->authenticate();
        $this->getJson('/api/v1/calendar?from=2025-12-01&to=2025-11-01')
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCalendarReturnsUnitsWithBookingsAndBlocks(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);

        $from = Carbon::today();
        $to = $from->copy()->addDays(30);

        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => $from->copy()->addDays(2)->toDateString(),
            'check_out' => $from->copy()->addDays(5)->toDateString(),
        ]);

        UnitDateBlock::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => $from->copy()->addDays(10)->toDateString(),
            'end_date' => $from->copy()->addDays(12)->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/calendar?from='.$from->toDateString().'&to='.$to->toDateString())
            ->assertOk()
            ->assertJsonStructure([
                'from',
                'to',
                'units' => [['uuid', 'name', 'bookings', 'blocks']],
            ]);

        $units = $response->json('units');
        $this->assertCount(1, $units);
        $this->assertCount(1, $units[0]['bookings']);
        $this->assertCount(1, $units[0]['blocks']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCalendarRejectsRangeAbove400Days(): void
    {
        $this->authenticate();
        $from = Carbon::today();
        $to = $from->copy()->addDays(401);

        $this->getJson('/api/v1/calendar?from='.$from->toDateString().'&to='.$to->toDateString())
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Date range is too large (max 400 days).']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCalendarReturnsEmptyUnitsWhenUserHasNone(): void
    {
        $this->authenticate();
        $from = Carbon::today();
        $to = $from->copy()->addDays(7);

        $this->getJson('/api/v1/calendar?from='.$from->toDateString().'&to='.$to->toDateString())
            ->assertOk()
            ->assertJson(['units' => []]);
    }
}
