<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Carbon;

class BookingTest extends ApiTestCase
{
    private function setupUnit(array $unitOverrides = []): Unit
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);

        return Unit::factory()->create(array_merge([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 4,
            'price_per_night' => 1500,
            'currency' => 'PHP',
        ], $unitOverrides));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexReturnsOnlyOwnedBookings(): void
    {
        $unit = $this->setupUnit();
        Booking::factory()->count(2)->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
        ]);
        Booking::factory()->create();

        $this->getJson('/api/v1/bookings')
            ->assertOk()
            ->assertJsonCount(2, 'bookings');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexSupportsPaginationMeta(): void
    {
        $unit = $this->setupUnit();
        Booking::factory()->count(3)->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
        ]);

        $this->getJson('/api/v1/bookings?page=1&perPage=2')
            ->assertOk()
            ->assertJsonStructure([
                'bookings',
                'meta' => ['currentPage', 'lastPage', 'perPage', 'total'],
            ])
            ->assertJsonPath('meta.perPage', 2)
            ->assertJsonPath('meta.total', 3);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testShowReturnsBookingWithPaymentSummary(): void
    {
        $unit = $this->setupUnit();
        $booking = Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
        ]);

        $this->getJson('/api/v1/bookings/'.$booking->uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $booking->uuid)
            ->assertJsonPath('data.reference', $booking->reference)
            ->assertJsonPath('data.paid_total', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testShowReturns404WhenNotOwner(): void
    {
        $this->authenticate();
        $foreign = Booking::factory()->create();

        $this->getJson('/api/v1/bookings/'.$foreign->uuid)
            ->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreCreatesBookingWithExplicitTotal(): void
    {
        $unit = $this->setupUnit();
        $checkIn = Carbon::now()->addDays(5)->toDateString();
        $checkOut = Carbon::now()->addDays(8)->toDateString();

        $this->postJson('/api/v1/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'John Smith',
            'guestEmail' => 'john@example.com',
            'guestPhone' => '+639170000000',
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'adults' => 2,
            'children' => 0,
            'totalPrice' => 4500,
        ])
            ->assertCreated()
            ->assertJsonPath('data.guest_name', 'John Smith')
            ->assertJsonPath('data.total_price', 4500)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('bookings', [
            'unit_uuid' => $unit->uuid,
            'guest_name' => 'John Smith',
            'user_uuid' => $this->authUser->uuid,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreValidatesPayload(): void
    {
        $this->setupUnit();

        $this->postJson('/api/v1/bookings', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'unitId', 'guestName', 'guestEmail', 'guestPhone',
                'checkIn', 'checkOut', 'adults', 'children',
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreRejectsWhenGuestsExceedMax(): void
    {
        $unit = $this->setupUnit(['max_guests' => 2]);

        $this->postJson('/api/v1/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'Too Many',
            'guestEmail' => 'g@example.com',
            'guestPhone' => '111',
            'checkIn' => Carbon::now()->addDays(5)->toDateString(),
            'checkOut' => Carbon::now()->addDays(7)->toDateString(),
            'adults' => 3,
            'children' => 1,
            'totalPrice' => 1000,
        ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreRejectsOverlappingBooking(): void
    {
        $unit = $this->setupUnit();

        Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::now()->addDays(10)->toDateString(),
            'check_out' => Carbon::now()->addDays(15)->toDateString(),
            'status' => Booking::STATUS_ASSIGNED,
        ]);

        $this->postJson('/api/v1/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'New Guest',
            'guestEmail' => 'new@example.com',
            'guestPhone' => '111',
            'checkIn' => Carbon::now()->addDays(12)->toDateString(),
            'checkOut' => Carbon::now()->addDays(14)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'totalPrice' => 1000,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateModifiesBooking(): void
    {
        $unit = $this->setupUnit();
        $booking = Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'guest_name' => 'Original Guest',
            'check_in' => Carbon::now()->addDays(5)->toDateString(),
            'check_out' => Carbon::now()->addDays(7)->toDateString(),
            'adults' => 2,
            'children' => 0,
        ]);

        $this->patchJson('/api/v1/bookings/'.$booking->uuid, [
            'guestName' => 'Updated Guest',
            'notes' => 'Late check-in',
        ])
            ->assertOk()
            ->assertJsonPath('data.guest_name', 'Updated Guest')
            ->assertJsonPath('data.notes', 'Late check-in');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDestroySoftDeletesBooking(): void
    {
        $unit = $this->setupUnit();
        $booking = Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
        ]);

        $this->deleteJson('/api/v1/bookings/'.$booking->uuid)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSoftDeleted('bookings', ['uuid' => $booking->uuid]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAvailableUnitsReturnsOnlyForPendingOrAccepted(): void
    {
        $unit = $this->setupUnit();
        $booking = Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'status' => Booking::STATUS_CHECKED_IN,
        ]);

        $this->getJson('/api/v1/bookings/'.$booking->uuid.'/available-units')
            ->assertOk()
            ->assertJson(['units' => []]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAvailableUnitsListsMatchingUnits(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);

        $unitTemplate = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
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

        $booking = Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unitTemplate->uuid,
            'status' => Booking::STATUS_PENDING,
            'check_in' => Carbon::now()->addDays(5)->toDateString(),
            'check_out' => Carbon::now()->addDays(7)->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/bookings/'.$booking->uuid.'/available-units')
            ->assertOk()
            ->assertJsonStructure(['units' => [['uuid', 'name']]]);

        $this->assertNotEmpty($response->json('units'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreComputesPricingWhenTotalOmitted(): void
    {
        $unit = $this->setupUnit();
        $checkIn = Carbon::now()->addDays(5)->toDateString();
        $checkOut = Carbon::now()->addDays(8)->toDateString();

        $this->postJson('/api/v1/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'Auto Total',
            'guestEmail' => 'auto@example.com',
            'guestPhone' => '+639170000000',
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'adults' => 1,
            'children' => 0,
        ])
            ->assertCreated()
            ->assertJsonPath('data.total_price', 4500);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreNormalizesInvalidSourceToManual(): void
    {
        $unit = $this->setupUnit();

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ])->postJson('/api/v1/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'Source Norm',
            'guestEmail' => 'src@example.com',
            'guestPhone' => '+639170000000',
            'checkIn' => Carbon::now()->addDays(2)->toDateString(),
            'checkOut' => Carbon::now()->addDays(4)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'totalPrice' => 3000,
            'source' => '   ',
        ])
            ->assertCreated()
            ->assertJsonPath('data.source', Booking::SOURCE_MANUAL);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAvailableUnitsReturnsEmptyWhenBookingHasNoUnit(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);
        $booking = Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'status' => Booking::STATUS_PENDING,
        ]);

        $unit->forceDelete();

        $this->getJson('/api/v1/bookings/'.$booking->uuid.'/available-units')
            ->assertOk()
            ->assertJson(['units' => []]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateRecomputesPricingWhenDatesChange(): void
    {
        $unit = $this->setupUnit();
        $booking = Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::now()->addDays(2)->toDateString(),
            'check_out' => Carbon::now()->addDays(4)->toDateString(),
            'total_price' => 3000,
            'adults' => 1,
            'children' => 0,
        ]);

        $this->patchJson('/api/v1/bookings/'.$booking->uuid, [
            'checkIn' => Carbon::now()->addDays(5)->toDateString(),
            'checkOut' => Carbon::now()->addDays(8)->toDateString(),
        ])
            ->assertOk()
            ->assertJsonPath('data.total_price', 4500);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdatePersistsAllOptionalFields(): void
    {
        $unit = $this->setupUnit();
        $booking = Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::now()->addDays(5)->toDateString(),
            'check_out' => Carbon::now()->addDays(7)->toDateString(),
            'adults' => 1,
            'children' => 0,
        ]);

        $this->patchJson('/api/v1/bookings/'.$booking->uuid, [
            'unitId' => $unit->uuid,
            'guestEmail' => 'newmail@example.com',
            'guestPhone' => '+639170000123',
            'adults' => 2,
            'children' => 1,
            'source' => 'AirBnB',
            'totalPrice' => 7500,
        ])
            ->assertOk()
            ->assertJsonPath('data.guest_email', 'newmail@example.com')
            ->assertJsonPath('data.guest_phone', '+639170000123')
            ->assertJsonPath('data.adults', 2)
            ->assertJsonPath('data.children', 1)
            ->assertJsonPath('data.source', 'airbnb')
            ->assertJsonPath('data.total_price', 7500);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateRejectsAssigningWhenBlocked(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 4,
        ]);

        $booking = Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::now()->addDays(5)->toDateString(),
            'check_out' => Carbon::now()->addDays(7)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'status' => Booking::STATUS_PENDING,
        ]);

        \App\Models\UnitDateBlock::create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::now()->addDays(4)->toDateString(),
            'end_date' => Carbon::now()->addDays(8)->toDateString(),
            'label' => 'Blocked',
        ]);

        $this->patchJson('/api/v1/bookings/'.$booking->uuid, [
            'status' => Booking::STATUS_ASSIGNED,
        ])
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateRejectsAcceptingWhenNoMatchingUnitAvailable(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 4,
            'bedrooms' => 1,
            'beds' => 1,
        ]);

        $booking = Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::now()->addDays(5)->toDateString(),
            'check_out' => Carbon::now()->addDays(7)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'status' => Booking::STATUS_PENDING,
        ]);

        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::now()->addDays(4)->toDateString(),
            'check_out' => Carbon::now()->addDays(8)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'status' => Booking::STATUS_ASSIGNED,
        ]);

        $this->patchJson('/api/v1/bookings/'.$booking->uuid, [
            'status' => Booking::STATUS_ACCEPTED,
        ])
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStorePropagatesPricingErrorWhenIntervalRulesFail(): void
    {
        $unit = $this->setupUnit();

        \App\Models\UnitRateInterval::create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'MinLos',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(60)->toDateString(),
            'min_los' => 7,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => null,
            'base_price' => 1500,
            'currency' => 'PHP',
        ]);

        $this->postJson('/api/v1/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'PriceErr',
            'guestEmail' => 'err@example.com',
            'guestPhone' => '+639170000000',
            'checkIn' => Carbon::now()->addDays(2)->toDateString(),
            'checkOut' => Carbon::now()->addDays(3)->toDateString(),
            'adults' => 1,
            'children' => 0,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdatePropagatesPricingErrorOnDateChange(): void
    {
        $unit = $this->setupUnit();
        $booking = Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::now()->addDays(15)->toDateString(),
            'check_out' => Carbon::now()->addDays(20)->toDateString(),
            'adults' => 1,
            'children' => 0,
        ]);

        \App\Models\UnitRateInterval::create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'MinLos',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(60)->toDateString(),
            'min_los' => 7,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => null,
            'base_price' => 1500,
            'currency' => 'PHP',
        ]);

        $this->patchJson('/api/v1/bookings/'.$booking->uuid, [
            'checkIn' => Carbon::now()->addDays(30)->toDateString(),
            'checkOut' => Carbon::now()->addDays(31)->toDateString(),
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateRejectsStatusTransitionWhenGuestsExceedUnitMax(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 1,
            'price_per_night' => 1000,
            'currency' => 'PHP',
        ]);

        $booking = Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::now()->addDays(5)->toDateString(),
            'check_out' => Carbon::now()->addDays(7)->toDateString(),
            'adults' => 4,
            'children' => 0,
            'status' => Booking::STATUS_PENDING,
        ]);

        $this->patchJson('/api/v1/bookings/'.$booking->uuid, [
            'status' => Booking::STATUS_ASSIGNED,
        ])->assertStatus(422);
    }
}
