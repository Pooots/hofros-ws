<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BookingExtraTest extends ApiTestCase
{
    private function setupUnit(array $unitOverrides = []): Unit
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);

        return Unit::factory()->create(array_merge([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 4,
            'bedrooms' => 1,
            'beds' => 1,
            'type' => 'Studio',
            'price_per_night' => 1500,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ], $unitOverrides));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexSearchReturnsMatchingBookings(): void
    {
        $unit = $this->setupUnit();
        Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'guest_name' => 'Search Target',
        ]);
        Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'guest_name' => 'Other Guest',
        ]);

        $this->getJson('/api/v1/bookings?q=Search&page=1&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexFilterByStatus(): void
    {
        $unit = $this->setupUnit();
        Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'status' => Booking::STATUS_ACCEPTED,
        ]);
        Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'status' => Booking::STATUS_PENDING,
        ]);

        $this->getJson('/api/v1/bookings?status=accepted')
            ->assertOk()
            ->assertJsonCount(1, 'bookings');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexCollapsesPortalBatchByDefault(): void
    {
        $unit = $this->setupUnit();
        $batchId = (string) Str::uuid();
        Booking::factory()->count(3)->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'portal_batch_id' => $batchId,
        ]);

        $this->getJson('/api/v1/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'bookings');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexExpandsPortalBatchWhenRequested(): void
    {
        $unit = $this->setupUnit();
        $batchId = (string) Str::uuid();
        Booking::factory()->count(3)->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'portal_batch_id' => $batchId,
        ]);

        $this->getJson('/api/v1/bookings?expandBatch=1')
            ->assertOk()
            ->assertJsonCount(3, 'bookings');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testShowIncludesBatchBookingsForPortalBatch(): void
    {
        $unit = $this->setupUnit();
        $batchId = (string) Str::uuid();
        $a = Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'portal_batch_id' => $batchId,
            'total_price' => 1000,
        ]);
        Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'portal_batch_id' => $batchId,
            'total_price' => 2500,
        ]);

        $this->getJson('/api/v1/bookings/'.$a->uuid)
            ->assertOk()
            ->assertJsonPath('data.portal_batch_id', $batchId)
            ->assertJsonPath('data.batch_total_price', 3500);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreUsesUnitPricingWhenTotalOmitted(): void
    {
        $unit = $this->setupUnit(['price_per_night' => 1200]);

        $checkIn = Carbon::today()->addDays(5)->toDateString();
        $checkOut = Carbon::today()->addDays(8)->toDateString();

        $this->postJson('/api/v1/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'NoPrice',
            'guestEmail' => 'np@example.com',
            'guestPhone' => '+639170000000',
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'adults' => 1,
            'children' => 0,
        ])
            ->assertCreated()
            ->assertJsonPath('data.total_price', 3600);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreRejectsWhenDatesBlocked(): void
    {
        $unit = $this->setupUnit();
        UnitDateBlock::create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::today()->addDays(5)->toDateString(),
            'end_date' => Carbon::today()->addDays(10)->toDateString(),
            'label' => 'Maintenance',
        ]);

        $this->postJson('/api/v1/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'X',
            'guestEmail' => 'x@example.com',
            'guestPhone' => '+639170000000',
            'checkIn' => Carbon::today()->addDays(6)->toDateString(),
            'checkOut' => Carbon::today()->addDays(8)->toDateString(),
            'adults' => 1,
            'children' => 0,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateChangesStatusForPortalBatch(): void
    {
        $unit = $this->setupUnit();
        $batchId = (string) Str::uuid();
        Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'portal_batch_id' => $batchId,
            'status' => Booking::STATUS_PENDING,
            'check_in' => Carbon::today()->addDays(5)->toDateString(),
            'check_out' => Carbon::today()->addDays(7)->toDateString(),
            'adults' => 1,
            'children' => 0,
        ]);
        $sibling = Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'portal_batch_id' => $batchId,
            'status' => Booking::STATUS_PENDING,
            'check_in' => Carbon::today()->addDays(5)->toDateString(),
            'check_out' => Carbon::today()->addDays(7)->toDateString(),
            'adults' => 1,
            'children' => 0,
        ]);

        $this->patchJson('/api/v1/bookings/'.$sibling->uuid, ['status' => Booking::STATUS_ACCEPTED])
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_ACCEPTED);

        $this->assertDatabaseHas('bookings', ['uuid' => $sibling->uuid, 'status' => Booking::STATUS_ACCEPTED]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateRecomputesTotalWhenDatesChangeAndNoTotalGiven(): void
    {
        $unit = $this->setupUnit(['price_per_night' => 1000]);
        $booking = Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(2)->toDateString(),
            'check_out' => Carbon::today()->addDays(4)->toDateString(),
            'total_price' => 2000,
            'adults' => 1,
            'children' => 0,
        ]);

        $this->patchJson('/api/v1/bookings/'.$booking->uuid, [
            'checkIn' => Carbon::today()->addDays(2)->toDateString(),
            'checkOut' => Carbon::today()->addDays(7)->toDateString(),
        ])
            ->assertOk()
            ->assertJsonPath('data.total_price', 5000);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateRejectsWhenCheckOutNotAfterCheckIn(): void
    {
        $unit = $this->setupUnit();
        $booking = Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(2)->toDateString(),
            'check_out' => Carbon::today()->addDays(5)->toDateString(),
            'adults' => 1,
            'children' => 0,
        ]);

        $this->patchJson('/api/v1/bookings/'.$booking->uuid, [
            'checkIn' => Carbon::today()->addDays(5)->toDateString(),
            'checkOut' => Carbon::today()->addDays(5)->toDateString(),
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateRejectsOverlapWhenAssigning(): void
    {
        $unit = $this->setupUnit();
        Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(2)->toDateString(),
            'check_out' => Carbon::today()->addDays(7)->toDateString(),
            'status' => Booking::STATUS_ASSIGNED,
            'adults' => 1,
            'children' => 0,
        ]);
        $pending = Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(3)->toDateString(),
            'check_out' => Carbon::today()->addDays(5)->toDateString(),
            'status' => Booking::STATUS_PENDING,
            'adults' => 1,
            'children' => 0,
        ]);

        $this->patchJson('/api/v1/bookings/'.$pending->uuid, ['status' => Booking::STATUS_ASSIGNED])
            ->assertStatus(422);
    }

    public function test_destroy_404_when_not_owner(): void
    {
        $this->authenticate();
        $foreign = Booking::factory()->create();
        $this->deleteJson('/api/v1/bookings/'.$foreign->uuid)
            ->assertStatus(404);
    }
}
