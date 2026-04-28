<?php

namespace Tests\Feature\Api;

use App\Models\BookingPortalConnection;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Carbon;

class PublicDirectPortalTest extends ApiTestCase
{
    private function publishedMerchant(string $merchantName = 'Acme Stays'): User
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
    public function testShowReturns404ForUnknownSlug(): void
    {
        $this->getJson('/api/v1/public/direct-portals/unknown-slug')
            ->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testShowReturns404WhenPortalNotPublished(): void
    {
        User::factory()->create(['merchant_name' => 'Hidden']);

        $this->getJson('/api/v1/public/direct-portals/hidden')
            ->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testShowReturnsPublishedPortal(): void
    {
        $user = $this->publishedMerchant('Acme Stays');

        $this->getJson('/api/v1/public/direct-portals/acme-stays')
            ->assertOk()
            ->assertJsonPath('data.merchantName', 'Acme Stays')
            ->assertJsonPath('data.headline', 'Welcome');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCalendarValidatesRange(): void
    {
        $this->publishedMerchant('Acme Stays');
        $this->getJson('/api/v1/public/direct-portals/acme-stays/calendar')
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCalendarReturnsEmptyWhenNoUnits(): void
    {
        $this->publishedMerchant('Acme Stays');
        $from = Carbon::today()->toDateString();
        $to = Carbon::today()->addDays(7)->toDateString();

        $this->getJson("/api/v1/public/direct-portals/acme-stays/calendar?from={$from}&to={$to}")
            ->assertOk()
            ->assertJson(['units' => []]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testQuoteReturnsSubtotalForValidRange(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        $checkIn = Carbon::today()->addDays(20)->toDateString();
        $checkOut = Carbon::today()->addDays(23)->toDateString();

        $this->getJson("/api/v1/public/direct-portals/acme-stays/quote?unitId={$unit->uuid}&checkIn={$checkIn}&checkOut={$checkOut}")
            ->assertOk()
            ->assertJsonPath('subtotalPrice', 3000)
            ->assertJsonPath('totalPrice', 3000)
            ->assertJsonPath('nights', 3)
            ->assertJsonPath('currency', 'PHP');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testQuoteValidatesPayload(): void
    {
        $this->publishedMerchant('Acme Stays');
        $this->getJson('/api/v1/public/direct-portals/acme-stays/quote')
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookCreatesPendingBooking(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 4,
            'price_per_night' => 1500,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        $payload = [
            'unitId' => $unit->uuid,
            'guestName' => 'Jane Doe',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '+639170001111',
            'checkIn' => Carbon::today()->addDays(30)->toDateString(),
            'checkOut' => Carbon::today()->addDays(33)->toDateString(),
            'adults' => 2,
            'children' => 0,
        ];

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', $payload)
            ->assertCreated()
            ->assertJsonStructure(['reference', 'unitCount', 'totalAmount', 'status']);

        $this->assertDatabaseHas('bookings', [
            'unit_uuid' => $unit->uuid,
            'guest_name' => 'Jane Doe',
            'status' => 'pending',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookValidatesPayload(): void
    {
        $this->publishedMerchant('Acme Stays');
        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [])
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookValidatesPhoneLength(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 2,
            'status' => Unit::STATUS_ACTIVE,
        ]);

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'Jane',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '12',
            'checkIn' => Carbon::today()->addDays(5)->toDateString(),
            'checkOut' => Carbon::today()->addDays(7)->toDateString(),
            'adults' => 1,
            'children' => 0,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookRejectsUnknownUnit(): void
    {
        $this->publishedMerchant('Acme Stays');

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
            'unitId' => '00000000-0000-0000-0000-000000000000',
            'guestName' => 'Jane',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '+639170001111',
            'checkIn' => Carbon::today()->addDays(5)->toDateString(),
            'checkOut' => Carbon::today()->addDays(7)->toDateString(),
            'adults' => 1,
            'children' => 0,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookRejectsWhenGuestCountExceedsUnitMax(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 2,
            'status' => Unit::STATUS_ACTIVE,
        ]);

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'Jane',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '+639170001111',
            'checkIn' => Carbon::today()->addDays(5)->toDateString(),
            'checkOut' => Carbon::today()->addDays(7)->toDateString(),
            'adults' => 5,
            'children' => 0,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookWithPromoCodeAppliesDiscount(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 4,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        \App\Models\PromoCode::create([
            'user_uuid' => $user->uuid,
            'code' => 'SAVE10',
            'discount_type' => \App\Models\PromoCode::TYPE_FIXED,
            'discount_value' => 500,
            'min_nights' => 1,
            'max_uses' => 5,
            'uses_count' => 0,
            'status' => \App\Models\PromoCode::STATUS_ACTIVE,
        ]);

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'Jane',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '+639170001111',
            'checkIn' => Carbon::today()->addDays(10)->toDateString(),
            'checkOut' => Carbon::today()->addDays(13)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'promoCode' => 'SAVE10',
        ])
            ->assertCreated()
            ->assertJsonPath('discountAmount', 500)
            ->assertJsonPath('totalAmount', 2500)
            ->assertJsonPath('promoCode', 'SAVE10');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookInvalidPromoCodeRejected(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 2,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'Jane',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '+639170001111',
            'checkIn' => Carbon::today()->addDays(5)->toDateString(),
            'checkOut' => Carbon::today()->addDays(7)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'promoCode' => 'NOPE',
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testQuoteReturns404ForUnknownUnit(): void
    {
        $this->publishedMerchant('Acme Stays');
        $checkIn = Carbon::today()->addDays(5)->toDateString();
        $checkOut = Carbon::today()->addDays(7)->toDateString();

        $this->getJson("/api/v1/public/direct-portals/acme-stays/quote?unitId=00000000-0000-0000-0000-000000000000&checkIn={$checkIn}&checkOut={$checkOut}")
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookRejectsWhenListedUnitFullyBooked(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 2,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        \App\Models\Booking::factory()->create([
            'user_uuid' => $user->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(20)->toDateString(),
            'check_out' => Carbon::today()->addDays(25)->toDateString(),
            'status' => \App\Models\Booking::STATUS_ACCEPTED,
            'adults' => 1,
            'children' => 0,
        ]);

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'Jane',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '+639170001111',
            'checkIn' => Carbon::today()->addDays(21)->toDateString(),
            'checkOut' => Carbon::today()->addDays(23)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'unitCount' => 1,
        ])->assertStatus(422)->assertJsonFragment(['message' => \App\Support\BookingStayConflict::guestPortalUnavailableMessage()]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookPropagatesPricingErrorWhenIntervalRulesFail(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 2,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        \App\Models\UnitRateInterval::create([
            'user_uuid' => $user->uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'Closed window',
            'start_date' => Carbon::today()->addDays(20)->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
            'base_price' => 1500,
            'currency' => 'PHP',
            'min_los' => 5,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => [],
            'closed_to_arrival' => true,
            'closed_to_departure' => false,
        ]);

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'Jane',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '+639170001111',
            'checkIn' => Carbon::today()->addDays(22)->toDateString(),
            'checkOut' => Carbon::today()->addDays(23)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'unitCount' => 1,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookFailsWhenPromoCodeRaceConsumesLastUse(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 2,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => \App\Models\Unit::STATUS_ACTIVE,
        ]);

        $promo = \App\Models\PromoCode::create([
            'user_uuid' => $user->uuid,
            'code' => 'RACEME',
            'discount_type' => \App\Models\PromoCode::TYPE_FIXED,
            'discount_value' => 100,
            'min_nights' => 1,
            'max_uses' => 1,
            'uses_count' => 0,
            'status' => \App\Models\PromoCode::STATUS_ACTIVE,
        ]);

        \App\Models\Booking::created(function (\App\Models\Booking $booking) use ($promo): void {
            if ($booking->source === \App\Models\Booking::SOURCE_DIRECT_PORTAL) {
                \App\Models\PromoCode::query()
                    ->where('uuid', $promo->uuid)
                    ->update(['uses_count' => 1]);
            }
        });

        try {
            $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
                'unitId' => $unit->uuid,
                'guestName' => 'Jane',
                'guestEmail' => 'jane@example.com',
                'guestPhone' => '+639170001111',
                'checkIn' => Carbon::today()->addDays(20)->toDateString(),
                'checkOut' => Carbon::today()->addDays(22)->toDateString(),
                'adults' => 1,
                'children' => 0,
                'unitCount' => 1,
                'promoCode' => 'RACEME',
            ])->assertStatus(422)
                ->assertJsonFragment(['message' => 'Promo code has reached its usage limit.']);
        } finally {
            \App\Models\Booking::flushEventListeners();
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookSucceedsAndConsumesPromoUsage(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 2,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        $promo = \App\Models\PromoCode::create([
            'user_uuid' => $user->uuid,
            'code' => 'WELCOME',
            'discount_type' => \App\Models\PromoCode::TYPE_FIXED,
            'discount_value' => 250,
            'min_nights' => 1,
            'max_uses' => 5,
            'uses_count' => 0,
            'status' => \App\Models\PromoCode::STATUS_ACTIVE,
        ]);

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'Jane',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '+639170001111',
            'checkIn' => Carbon::today()->addDays(20)->toDateString(),
            'checkOut' => Carbon::today()->addDays(22)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'unitCount' => 1,
            'promoCode' => 'WELCOME',
        ])->assertCreated();

        $this->assertSame(1, $promo->fresh()->uses_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookRejectsWhenInsufficientUnitsAvailable(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 2,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'Jane',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '+639170001111',
            'checkIn' => Carbon::today()->addDays(20)->toDateString(),
            'checkOut' => Carbon::today()->addDays(22)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'unitCount' => 5,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookRejectsInactiveUnit(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 2,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_INACTIVE,
        ]);

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'Jane',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '+639170001111',
            'checkIn' => Carbon::today()->addDays(5)->toDateString(),
            'checkOut' => Carbon::today()->addDays(7)->toDateString(),
            'adults' => 1,
            'children' => 0,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookRejectsWhenPromoCodeAtMaxUses(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'max_guests' => 4,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        \App\Models\PromoCode::create([
            'user_uuid' => $user->uuid,
            'code' => 'MAXED',
            'discount_type' => \App\Models\PromoCode::TYPE_FIXED,
            'discount_value' => 100,
            'min_nights' => 1,
            'max_uses' => 1,
            'uses_count' => 1,
            'status' => \App\Models\PromoCode::STATUS_ACTIVE,
        ]);

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
            'unitId' => $unit->uuid,
            'guestName' => 'Jane',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '+639170001111',
            'checkIn' => Carbon::today()->addDays(10)->toDateString(),
            'checkOut' => Carbon::today()->addDays(13)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'promoCode' => 'MAXED',
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookSucceedsForMultipleSiblingUnits(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $listed = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);
        Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'price_per_night' => 1500,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
            'unitId' => $listed->uuid,
            'guestName' => 'Jane',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '+639170001111',
            'checkIn' => Carbon::today()->addDays(20)->toDateString(),
            'checkOut' => Carbon::today()->addDays(22)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'unitCount' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('unitCount', 2)
            ->assertJsonStructure(['references']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookRejectsWhenSomeUnitsBusy(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $listed = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);
        $sibling = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        \App\Models\Booking::factory()->create([
            'user_uuid' => $user->uuid,
            'unit_uuid' => $sibling->uuid,
            'check_in' => Carbon::today()->addDays(20)->toDateString(),
            'check_out' => Carbon::today()->addDays(23)->toDateString(),
            'status' => \App\Models\Booking::STATUS_ACCEPTED,
            'adults' => 1,
            'children' => 0,
        ]);

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
            'unitId' => $listed->uuid,
            'guestName' => 'Jane',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '+639170001111',
            'checkIn' => Carbon::today()->addDays(21)->toDateString(),
            'checkOut' => Carbon::today()->addDays(22)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'unitCount' => 2,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookMultipleUnitsWithPromoDistributesDiscount(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $listed = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);
        Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'price_per_night' => 2000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        \App\Models\PromoCode::create([
            'user_uuid' => $user->uuid,
            'code' => 'GROUP25',
            'discount_type' => \App\Models\PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 25,
            'min_nights' => 1,
            'max_uses' => null,
            'uses_count' => 0,
            'status' => \App\Models\PromoCode::STATUS_ACTIVE,
        ]);

        $this->postJson('/api/v1/public/direct-portals/acme-stays/bookings', [
            'unitId' => $listed->uuid,
            'guestName' => 'Jane',
            'guestEmail' => 'jane@example.com',
            'guestPhone' => '+639170001111',
            'checkIn' => Carbon::today()->addDays(20)->toDateString(),
            'checkOut' => Carbon::today()->addDays(22)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'unitCount' => 2,
            'promoCode' => 'GROUP25',
        ])
            ->assertCreated()
            ->assertJsonPath('promoCode', 'GROUP25')
            ->assertJsonPath('unitCount', 2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testQuoteRejectsWhenListedUnitFullyBooked(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        \App\Models\Booking::factory()->create([
            'user_uuid' => $user->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::today()->addDays(20)->toDateString(),
            'check_out' => Carbon::today()->addDays(25)->toDateString(),
            'status' => \App\Models\Booking::STATUS_ACCEPTED,
            'adults' => 1,
            'children' => 0,
        ]);

        $checkIn = Carbon::today()->addDays(21)->toDateString();
        $checkOut = Carbon::today()->addDays(23)->toDateString();

        $this->getJson("/api/v1/public/direct-portals/acme-stays/quote?unitId={$unit->uuid}&checkIn={$checkIn}&checkOut={$checkOut}")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => \App\Support\BookingStayConflict::guestPortalUnavailableMessage()]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testQuotePropagatesPricingError(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        \App\Models\UnitRateInterval::create([
            'user_uuid' => $user->uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'Closed window',
            'start_date' => Carbon::today()->addDays(20)->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
            'base_price' => 1500,
            'currency' => 'PHP',
            'min_los' => 5,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => [],
            'closed_to_arrival' => true,
            'closed_to_departure' => false,
        ]);

        $checkIn = Carbon::today()->addDays(22)->toDateString();
        $checkOut = Carbon::today()->addDays(23)->toDateString();

        $this->getJson("/api/v1/public/direct-portals/acme-stays/quote?unitId={$unit->uuid}&checkIn={$checkIn}&checkOut={$checkOut}")
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testQuoteRejectsInactiveUnit(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'price_per_night' => 1000,
            'status' => Unit::STATUS_INACTIVE,
        ]);

        $checkIn = Carbon::today()->addDays(5)->toDateString();
        $checkOut = Carbon::today()->addDays(7)->toDateString();

        $this->getJson("/api/v1/public/direct-portals/acme-stays/quote?unitId={$unit->uuid}&checkIn={$checkIn}&checkOut={$checkOut}")
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testQuoteRejectsWhenSomeUnitsBusy(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $listed = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);
        $sibling = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        \App\Models\Booking::factory()->create([
            'user_uuid' => $user->uuid,
            'unit_uuid' => $sibling->uuid,
            'check_in' => Carbon::today()->addDays(20)->toDateString(),
            'check_out' => Carbon::today()->addDays(23)->toDateString(),
            'status' => \App\Models\Booking::STATUS_ACCEPTED,
            'adults' => 1,
            'children' => 0,
        ]);

        $checkIn = Carbon::today()->addDays(21)->toDateString();
        $checkOut = Carbon::today()->addDays(22)->toDateString();

        $this->getJson("/api/v1/public/direct-portals/acme-stays/quote?unitId={$listed->uuid}&checkIn={$checkIn}&checkOut={$checkOut}&unitCount=2")
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testQuoteRejectsExpiredPromoCode(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        \App\Models\PromoCode::create([
            'user_uuid' => $user->uuid,
            'code' => 'EXPIRED',
            'discount_type' => \App\Models\PromoCode::TYPE_FIXED,
            'discount_value' => 100,
            'min_nights' => 1,
            'max_uses' => 1,
            'uses_count' => 5,
            'status' => \App\Models\PromoCode::STATUS_ACTIVE,
        ]);

        $checkIn = Carbon::today()->addDays(5)->toDateString();
        $checkOut = Carbon::today()->addDays(7)->toDateString();

        $this->getJson("/api/v1/public/direct-portals/acme-stays/quote?unitId={$unit->uuid}&checkIn={$checkIn}&checkOut={$checkOut}&promoCode=EXPIRED")
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testQuoteReturnsPromoDiscount(): void
    {
        $user = $this->publishedMerchant('Acme Stays');
        $property = Property::factory()->create(['user_uuid' => $user->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $user->uuid,
            'property_uuid' => $property->uuid,
            'price_per_night' => 1000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ]);

        \App\Models\PromoCode::create([
            'user_uuid' => $user->uuid,
            'code' => 'PCT15',
            'discount_type' => \App\Models\PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 15,
            'min_nights' => 1,
            'max_uses' => null,
            'uses_count' => 0,
            'status' => \App\Models\PromoCode::STATUS_ACTIVE,
        ]);

        $checkIn = Carbon::today()->addDays(10)->toDateString();
        $checkOut = Carbon::today()->addDays(13)->toDateString();

        $this->getJson("/api/v1/public/direct-portals/acme-stays/quote?unitId={$unit->uuid}&checkIn={$checkIn}&checkOut={$checkOut}&promoCode=PCT15")
            ->assertOk()
            ->assertJsonPath('subtotalPrice', 3000)
            ->assertJsonPath('discountAmount', 450)
            ->assertJsonPath('totalPrice', 2550)
            ->assertJsonPath('promoCode', 'PCT15');
    }
}
