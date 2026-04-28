<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingPortalConnection;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use App\Models\User;
use Illuminate\Support\Carbon;

class PublicDirectPortalCalendarTest extends ApiTestCase
{
    private function publishedMerchant(string $merchantName = 'Calendar Co'): User
    {
        $user = User::factory()->create(['merchant_name' => $merchantName]);
        BookingPortalConnection::create([
            'user_uuid' => $user->uuid,
            'portal_key' => 'direct_website',
            'is_connected' => true,
            'is_active' => true,
            'guest_portal_live' => true,
            'guest_portal_design_completed' => true,
            'guest_portal_headline' => 'Welcome',
            'guest_portal_message' => 'Hi there',
            'listing_count' => 1,
        ]);

        return $user;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCalendarReturnsBookingsAndBlocksForUnits(): void
    {
        $user = $this->publishedMerchant();
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'price_per_night' => 1500,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        Booking::factory()->create([
            'user_uuid' => $user->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(2)->toDateString(),
            'check_out' => Carbon::today()->addDays(5)->toDateString(),
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        UnitDateBlock::create([
            'user_uuid' => $user->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::today()->addDays(7)->toDateString(),
            'end_date' => Carbon::today()->addDays(9)->toDateString(),
            'label' => 'Maintenance',
        ]);

        $from = Carbon::today()->toDateString();
        $to = Carbon::today()->addDays(15)->toDateString();

        $this->getJson("/api/v1/public/direct-portals/calendar-co/calendar?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonStructure(['units' => [['uuid', 'bookings', 'blocks']]])
            ->assertJsonPath('units.0.bookings.0.status', Booking::STATUS_ACCEPTED)
            ->assertJsonPath('units.0.blocks.0.label', 'Maintenance');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCalendarForSpecificUnitReturns404WhenUnitNotFound(): void
    {
        $user = $this->publishedMerchant();
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'status' => Unit::STATUS_ACTIVE,
        ]);

        $from = Carbon::today()->toDateString();
        $to = Carbon::today()->addDays(7)->toDateString();
        $missingUuid = '00000000-0000-0000-0000-000000000000';

        $this->getJson("/api/v1/public/direct-portals/calendar-co/calendar?from={$from}&to={$to}&unitId={$missingUuid}")
            ->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCalendarRejectsExcessiveRange(): void
    {
        $this->publishedMerchant();
        $from = Carbon::today()->toDateString();
        $to = Carbon::today()->addDays(500)->toDateString();

        $this->getJson("/api/v1/public/direct-portals/calendar-co/calendar?from={$from}&to={$to}")
            ->assertStatus(422);
    }
}
