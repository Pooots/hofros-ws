<?php

namespace Tests\Unit\Support;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use App\Models\User;
use App\Support\BookingStayConflict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingStayConflictTest extends TestCase
{
    use RefreshDatabase;

    private function makeUnit(User $owner, array $overrides = []): Unit
    {
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);

        return Unit::factory()->create(array_merge([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 4,
            'bedrooms' => 1,
            'beds' => 1,
            'type' => 'Studio',
            'status' => Unit::STATUS_ACTIVE,
        ], $overrides));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testHasOverlappingBookingDetectsAssignedStay(): void
    {
        $owner = User::factory()->create();
        $unit = $this->makeUnit($owner);
        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(5)->toDateString(),
            'check_out' => Carbon::today()->addDays(8)->toDateString(),
            'status' => Booking::STATUS_ASSIGNED,
        ]);

        $start = Carbon::today()->addDays(6);
        $end = Carbon::today()->addDays(7);

        $this->assertTrue(BookingStayConflict::hasOverlappingBooking($owner->uuid, $unit->uuid, $start, $end, null));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testHasOverlappingBookingIgnoresPendingStatus(): void
    {
        $owner = User::factory()->create();
        $unit = $this->makeUnit($owner);
        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(5)->toDateString(),
            'check_out' => Carbon::today()->addDays(8)->toDateString(),
            'status' => Booking::STATUS_PENDING,
        ]);

        $this->assertFalse(BookingStayConflict::hasOverlappingBooking(
            $owner->uuid,
            $unit->uuid,
            Carbon::today()->addDays(6),
            Carbon::today()->addDays(7),
            null
        ));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testHasOverlappingBookingExcludesSpecifiedUuid(): void
    {
        $owner = User::factory()->create();
        $unit = $this->makeUnit($owner);
        $existing = Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(5)->toDateString(),
            'check_out' => Carbon::today()->addDays(8)->toDateString(),
            'status' => Booking::STATUS_ASSIGNED,
        ]);

        $this->assertFalse(BookingStayConflict::hasOverlappingBooking(
            $owner->uuid,
            $unit->uuid,
            Carbon::today()->addDays(6),
            Carbon::today()->addDays(7),
            $existing->uuid
        ));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testHasOverlappingBlockDetectsBlock(): void
    {
        $owner = User::factory()->create();
        $unit = $this->makeUnit($owner);
        UnitDateBlock::create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::today()->addDays(2)->toDateString(),
            'end_date' => Carbon::today()->addDays(6)->toDateString(),
            'label' => 'Maintenance',
        ]);

        $this->assertTrue(BookingStayConflict::hasOverlappingBlock(
            $owner->uuid,
            $unit->uuid,
            Carbon::today()->addDays(3),
            Carbon::today()->addDays(5)
        ));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBlockOverlapsFirmBookingDetectsAccepted(): void
    {
        $owner = User::factory()->create();
        $unit = $this->makeUnit($owner);
        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(2)->toDateString(),
            'check_out' => Carbon::today()->addDays(5)->toDateString(),
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        $this->assertTrue(BookingStayConflict::blockOverlapsFirmBooking(
            $owner->uuid,
            $unit->uuid,
            Carbon::today()->addDays(3),
            Carbon::today()->addDays(4)
        ));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveDirectPortalUnitReturnsListedWhenFree(): void
    {
        $owner = User::factory()->create();
        $unit = $this->makeUnit($owner);

        $result = BookingStayConflict::resolveDirectPortalUnit(
            $owner->uuid,
            $unit,
            Carbon::today()->addDays(2),
            Carbon::today()->addDays(4)
        );

        $this->assertNotNull($result);
        $this->assertSame($unit->uuid, $result->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveDirectPortalUnitPicksAlternateWhenListedBusy(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $listed = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'status' => Unit::STATUS_ACTIVE,
        ]);
        $alt = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'status' => Unit::STATUS_ACTIVE,
        ]);

        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $listed->uuid,
            'check_in' => Carbon::today()->addDays(2)->toDateString(),
            'check_out' => Carbon::today()->addDays(6)->toDateString(),
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        $picked = BookingStayConflict::resolveDirectPortalUnit(
            $owner->uuid,
            $listed,
            Carbon::today()->addDays(3),
            Carbon::today()->addDays(5)
        );

        $this->assertNotNull($picked);
        $this->assertSame($alt->uuid, $picked->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveDirectPortalBookUnitsReturnsZeroForInvalidCount(): void
    {
        $owner = User::factory()->create();
        $unit = $this->makeUnit($owner);

        $this->assertSame([], BookingStayConflict::resolveDirectPortalBookUnits(
            $owner->uuid,
            $unit,
            Carbon::today()->addDays(2),
            Carbon::today()->addDays(4),
            0
        ));

        $this->assertSame([], BookingStayConflict::resolveDirectPortalBookUnits(
            $owner->uuid,
            $unit,
            Carbon::today()->addDays(2),
            Carbon::today()->addDays(4),
            BookingStayConflict::MAX_PORTAL_UNITS_PER_BOOKING + 1
        ));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveDirectPortalBookUnitsReturnsUniqueUnits(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $listed = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'status' => Unit::STATUS_ACTIVE,
        ]);
        Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'status' => Unit::STATUS_ACTIVE,
        ]);

        $picks = BookingStayConflict::resolveDirectPortalBookUnits(
            $owner->uuid,
            $listed,
            Carbon::today()->addDays(2),
            Carbon::today()->addDays(4),
            2
        );

        $this->assertCount(2, $picks);
        $this->assertNotEquals($picks[0]->uuid, $picks[1]->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveDirectPortalBookUnitsShortWhenNoPropertyForMulti(): void
    {
        $owner = User::factory()->create();
        $listed = new Unit([
            'user_uuid' => $owner->uuid,
            'property_uuid' => null,
            'name' => 'Loose',
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'price_per_night' => 1500,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);
        $listed->uuid = (string) \Illuminate\Support\Str::uuid();

        $picks = BookingStayConflict::resolveDirectPortalBookUnits(
            $owner->uuid,
            $listed,
            Carbon::today()->addDays(2),
            Carbon::today()->addDays(4),
            2
        );

        $this->assertSame([], $picks);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUserFacingMessagesDescribeUnavailability(): void
    {
        $this->assertNotEmpty(BookingStayConflict::guestPortalUnavailableMessage());
        $this->assertStringContainsString('1', BookingStayConflict::guestPortalInsufficientUnitsMessage(1, 3));
        $this->assertStringContainsString('3', BookingStayConflict::guestPortalInsufficientUnitsMessage(1, 3));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGuestPortalStayUnavailableWhenBlockExists(): void
    {
        $owner = User::factory()->create();
        $unit = $this->makeUnit($owner);
        UnitDateBlock::create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::today()->addDays(2)->toDateString(),
            'end_date' => Carbon::today()->addDays(6)->toDateString(),
            'label' => 'Closure',
        ]);

        $this->assertTrue(BookingStayConflict::guestPortalStayIsUnavailable(
            $owner->uuid,
            $unit->uuid,
            Carbon::today()->addDays(3),
            Carbon::today()->addDays(5)
        ));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveDirectPortalUnitReturnsNullWhenNoPropertyAndListedBusy(): void
    {
        $owner = User::factory()->create();
        $listed = new Unit([
            'user_uuid' => $owner->uuid,
            'property_uuid' => null,
            'name' => 'Loose',
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'price_per_night' => 1500,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);
        $listed->uuid = (string) \Illuminate\Support\Str::uuid();

        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $listed->uuid,
            'check_in' => Carbon::today()->addDays(2)->toDateString(),
            'check_out' => Carbon::today()->addDays(6)->toDateString(),
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        $picked = BookingStayConflict::resolveDirectPortalUnit(
            $owner->uuid,
            $listed,
            Carbon::today()->addDays(3),
            Carbon::today()->addDays(5)
        );

        $this->assertNull($picked);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveDirectPortalUnitMatchesNullTypeListings(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $listed = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'status' => Unit::STATUS_ACTIVE,
        ]);

        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $listed->uuid,
            'check_in' => Carbon::today()->addDays(2)->toDateString(),
            'check_out' => Carbon::today()->addDays(6)->toDateString(),
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        $listed->setAttribute('type', null);

        $picked = BookingStayConflict::resolveDirectPortalUnit(
            $owner->uuid,
            $listed,
            Carbon::today()->addDays(3),
            Carbon::today()->addDays(5)
        );

        $this->assertNull($picked);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveDirectPortalUnitReturnsNullWhenAllSiblingsBusy(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $listed = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'status' => Unit::STATUS_ACTIVE,
        ]);
        $sibling = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'status' => Unit::STATUS_ACTIVE,
        ]);

        foreach ([$listed, $sibling] as $u) {
            Booking::factory()->create([
                'user_uuid' => $owner->uuid,
                'unit_uuid' => $u->uuid,
                'check_in' => Carbon::today()->addDays(2)->toDateString(),
                'check_out' => Carbon::today()->addDays(6)->toDateString(),
                'status' => Booking::STATUS_ACCEPTED,
            ]);
        }

        $picked = BookingStayConflict::resolveDirectPortalUnit(
            $owner->uuid,
            $listed,
            Carbon::today()->addDays(3),
            Carbon::today()->addDays(5)
        );

        $this->assertNull($picked);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveDirectPortalBookUnitsBreaksEarlyWhenNotEnough(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $listed = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'status' => Unit::STATUS_ACTIVE,
        ]);
        Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'status' => Unit::STATUS_ACTIVE,
        ]);

        $picks = BookingStayConflict::resolveDirectPortalBookUnits(
            $owner->uuid,
            $listed,
            Carbon::today()->addDays(2),
            Carbon::today()->addDays(4),
            5
        );

        $this->assertCount(2, $picks);
    }
}
