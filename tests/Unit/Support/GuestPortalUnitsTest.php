<?php

namespace Tests\Unit\Support;

use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitRateInterval;
use App\Models\User;
use App\Support\GuestPortalUnits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GuestPortalUnitsTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPublicPayloadReturnsOnlyActiveUnitsForUser(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $property = Property::factory()->create(['user_uuid' => $owner->uuid, 'property_name' => 'Beach House']);
        $listed = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'name' => 'Aurora Suite',
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'price_per_night' => 1500,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
            'description' => 'Cozy seaside studio.',
            'details' => 'Wifi, AC',
            'images' => ['https://example.test/a.jpg', '', null, 'https://example.test/b.jpg'],
        ]);

        Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'status' => 'inactive',
        ]);
        $otherProperty = Property::factory()->create(['user_uuid' => $other->uuid]);
        Unit::factory()->create([
            'user_uuid' => $other->uuid,
            'property_uuid' => $otherProperty->uuid,
            'status' => Unit::STATUS_ACTIVE,
        ]);

        $payload = GuestPortalUnits::publicPayloadForUserUuid($owner->uuid);

        $this->assertCount(1, $payload);
        $row = $payload[0];

        $this->assertSame($listed->uuid, $row['uuid']);
        $this->assertSame('Aurora Suite', $row['name']);
        $this->assertSame('Studio', $row['type']);
        $this->assertSame(2, $row['maxGuests']);
        $this->assertSame(1500.0, $row['pricePerNight']);
        $this->assertSame(1500.0, $row['pricePerNightMax']);
        $this->assertSame('PHP', $row['currency']);
        $this->assertSame('Beach House', $row['propertyName']);
        $this->assertSame('Cozy seaside studio.', $row['description']);
        $this->assertSame('Wifi, AC', $row['details']);
        $this->assertSame(['https://example.test/a.jpg', 'https://example.test/b.jpg'], $row['images']);
        $this->assertArrayHasKey('weekSchedule', $row);
        $this->assertCount(7, $row['weekSchedule']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPublicPayloadUsesRateIntervalsForMinMaxWhenActive(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'status' => Unit::STATUS_ACTIVE,
            'price_per_night' => 2000,
            'currency' => 'PHP',
            'images' => null,
        ]);

        UnitRateInterval::create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(60)->toDateString(),
            'days_of_week' => [
                'sun' => true,
                'mon' => true,
                'tue' => true,
                'wed' => true,
                'thu' => true,
                'fri' => true,
                'sat' => true,
            ],
            'base_price' => 2200,
            'day_prices' => [
                'fri' => 3500,
                'sat' => 3500,
            ],
            'min_los' => 1,
        ]);

        $payload = GuestPortalUnits::publicPayloadForUserUuid($owner->uuid);
        $this->assertCount(1, $payload);
        $this->assertSame(2200.0, $payload[0]['pricePerNight']);
        $this->assertSame(3500.0, $payload[0]['pricePerNightMax']);
        $this->assertSame([], $payload[0]['images']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPublicPayloadReturnsEmptyForUserWithNoUnits(): void
    {
        $owner = User::factory()->create();

        $payload = GuestPortalUnits::publicPayloadForUserUuid($owner->uuid);
        $this->assertSame([], $payload);
    }
}
