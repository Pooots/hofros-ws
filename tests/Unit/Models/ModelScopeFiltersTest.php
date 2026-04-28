<?php

namespace Tests\Unit\Models;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\NotificationPreference;
use App\Models\Property;
use App\Models\PromoCode;
use App\Models\TeamMember;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use App\Models\UnitDiscount;
use App\Models\UnitRateInterval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModelScopeFiltersTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitScopeFiltersMatchPropertyStatusAndKeyword(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $other = Property::factory()->create(['user_uuid' => $owner->uuid]);

        Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'name' => 'Studio Apartment',
            'description' => 'Cozy place',
            'details' => 'WiFi included',
            'status' => Unit::STATUS_ACTIVE,
        ]);
        Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $other->uuid,
            'name' => 'Different unit',
            'status' => Unit::STATUS_INACTIVE,
        ]);

        $rows = Unit::query()
            ->filters([
                'user_uuid' => $owner->uuid,
                'property_uuid' => $property->uuid,
                'status' => Unit::STATUS_ACTIVE,
                'q' => 'Cozy',
            ])
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Studio Apartment', $rows->first()->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookingScopeFiltersByStatusAndUnit(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);

        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'status' => Booking::STATUS_CHECKED_OUT,
        ]);
        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'status' => Booking::STATUS_PENDING,
        ]);

        $rows = Booking::query()
            ->filters([
                'user_uuid' => $owner->uuid,
                'unit_uuid' => $unit->uuid,
                'status' => Booking::STATUS_CHECKED_OUT,
            ])
            ->get();

        $this->assertCount(1, $rows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookingScopeFiltersStatusAllReturnsEverything(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);

        Booking::factory()->count(2)->create(['user_uuid' => $owner->uuid, 'unit_uuid' => $unit->uuid]);

        $rows = Booking::query()->filters(['user_uuid' => $owner->uuid, 'status' => 'all'])->get();
        $this->assertCount(2, $rows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookingScopeFiltersByPortalBatchId(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);
        $batchId = (string) Str::uuid();
        Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'portal_batch_id' => $batchId,
        ]);
        Booking::factory()->create(['user_uuid' => $owner->uuid, 'unit_uuid' => $unit->uuid]);

        $rows = Booking::query()->filters(['portal_batch_id' => $batchId])->get();
        $this->assertCount(1, $rows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPromoCodeScopeFiltersStatusAndSearch(): void
    {
        $owner = User::factory()->create();
        PromoCode::factory()->create(['user_uuid' => $owner->uuid, 'code' => 'SUMMER15', 'status' => PromoCode::STATUS_ACTIVE]);
        PromoCode::factory()->create(['user_uuid' => $owner->uuid, 'code' => 'WINTER20', 'status' => PromoCode::STATUS_INACTIVE]);

        $rows = PromoCode::query()
            ->filters(['user_uuid' => $owner->uuid, 'status' => 'active', 'q' => 'SUM'])
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('SUMMER15', $rows->first()->code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testTeamMemberScopeFiltersRoleAndSearch(): void
    {
        $owner = User::factory()->create();
        TeamMember::create([
            'owner_user_uuid' => $owner->uuid,
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'role' => TeamMember::ROLE_MANAGER,
        ]);
        TeamMember::create([
            'owner_user_uuid' => $owner->uuid,
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'role' => TeamMember::ROLE_STAFF,
        ]);

        $rows = TeamMember::query()
            ->filters(['owner_user_uuid' => $owner->uuid, 'role' => TeamMember::ROLE_MANAGER, 'q' => 'alice'])
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows->first()->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testTeamMemberEnsureOwnerRowCreatesAdminRow(): void
    {
        $owner = User::factory()->create([
            'first_name' => 'Owner',
            'last_name' => 'McAdmin',
        ]);

        $row = TeamMember::ensureOwnerRow($owner);

        $this->assertSame($owner->email, $row->email);
        $this->assertSame(TeamMember::ROLE_ADMIN, $row->role);
        $again = TeamMember::ensureOwnerRow($owner);
        $this->assertSame($row->uuid, $again->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPropertyScopeFiltersSearch(): void
    {
        $owner = User::factory()->create();
        Property::factory()->create(['user_uuid' => $owner->uuid, 'property_name' => 'Sunset Beach']);
        Property::factory()->create(['user_uuid' => $owner->uuid, 'property_name' => 'Mountain Lodge']);

        $rows = Property::query()->filters(['user_uuid' => $owner->uuid, 'q' => 'Sunset'])->get();

        $this->assertCount(1, $rows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitDateBlockScopeFiltersRange(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);
        UnitDateBlock::create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::today()->addDays(2)->toDateString(),
            'end_date' => Carbon::today()->addDays(5)->toDateString(),
            'label' => 'Maintenance',
        ]);
        UnitDateBlock::create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::today()->addDays(20)->toDateString(),
            'end_date' => Carbon::today()->addDays(25)->toDateString(),
            'label' => 'Late',
        ]);

        $rows = UnitDateBlock::query()
            ->filters([
                'user_uuid' => $owner->uuid,
                'unit_uuid' => $unit->uuid,
                'from' => Carbon::today()->toDateString(),
                'to' => Carbon::today()->addDays(7)->toDateString(),
            ])
            ->get();

        $this->assertCount(1, $rows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitDiscountScopeFilters(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);

        UnitDiscount::create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'discount_type' => UnitDiscount::TYPE_EARLY_BIRD,
            'discount_percent' => 10,
            'status' => UnitDiscount::STATUS_ACTIVE,
        ]);
        UnitDiscount::create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'discount_type' => UnitDiscount::TYPE_LONG_STAY,
            'discount_percent' => 20,
            'status' => UnitDiscount::STATUS_INACTIVE,
        ]);

        $rows = UnitDiscount::query()
            ->filters([
                'user_uuid' => $owner->uuid,
                'unit_uuid' => $unit->uuid,
                'status' => UnitDiscount::STATUS_ACTIVE,
                'discount_type' => UnitDiscount::TYPE_EARLY_BIRD,
            ])
            ->get();

        $this->assertCount(1, $rows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitRateIntervalScopeFilters(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);

        UnitRateInterval::factory()->create(['user_uuid' => $owner->uuid, 'unit_uuid' => $unit->uuid]);
        UnitRateInterval::factory()->create();

        $rows = UnitRateInterval::query()
            ->filters(['user_uuid' => $owner->uuid, 'unit_uuid' => $unit->uuid])
            ->get();

        $this->assertCount(1, $rows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookingPaymentScopeFiltersCombinations(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);
        $booking = Booking::factory()->create(['user_uuid' => $owner->uuid, 'unit_uuid' => $unit->uuid]);

        BookingPayment::factory()->create([
            'booking_uuid' => $booking->uuid,
            'user_uuid' => $owner->uuid,
            'transaction_kind' => BookingPayment::KIND_PAYMENT,
        ]);
        BookingPayment::factory()->create([
            'booking_uuid' => $booking->uuid,
            'user_uuid' => $owner->uuid,
            'transaction_kind' => BookingPayment::KIND_REFUND,
        ]);

        $rows = BookingPayment::query()
            ->filters(['booking_uuids' => [$booking->uuid], 'transaction_kind' => BookingPayment::KIND_REFUND])
            ->get();

        $this->assertCount(1, $rows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUserRelationshipsResolve(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);
        Booking::factory()->create(['user_uuid' => $owner->uuid, 'unit_uuid' => $unit->uuid]);

        $this->assertCount(1, $owner->properties);
        $this->assertCount(1, $owner->units);
        $this->assertCount(1, $owner->bookings);
        $this->assertNull($owner->notificationPreference);
        $this->assertCount(0, $owner->ownedTeamMembers);
        $this->assertCount(0, $owner->bookingPortalConnections);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testNotificationPreferenceEnsureForUserCreatesDefaults(): void
    {
        $user = User::factory()->create();
        $row = NotificationPreference::ensureForUser($user);
        $this->assertSame($user->uuid, $row->user_uuid);
        $repeat = NotificationPreference::ensureForUser($user);
        $this->assertSame($row->uuid, $repeat->uuid);
        $this->assertSame($user->uuid, $row->user->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPromoCodeBelongsToUser(): void
    {
        $owner = User::factory()->create();
        $promo = PromoCode::factory()->create(['user_uuid' => $owner->uuid]);

        $this->assertSame($owner->uuid, $promo->user->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitDateBlockBelongsToUserAndUnit(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);
        $block = UnitDateBlock::create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDay()->toDateString(),
            'label' => 'Closure',
        ]);

        $this->assertSame($owner->uuid, $block->user->uuid);
        $this->assertSame($unit->uuid, $block->unit->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitDiscountBelongsToUserAndUnit(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);
        $discount = UnitDiscount::create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'discount_type' => UnitDiscount::TYPE_LONG_STAY,
            'discount_percent' => 10,
            'status' => UnitDiscount::STATUS_ACTIVE,
        ]);

        $this->assertSame($owner->uuid, $discount->user->uuid);
        $this->assertSame($unit->uuid, $discount->unit->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testTeamMemberBelongsToOwner(): void
    {
        $owner = User::factory()->create();
        $member = TeamMember::create([
            'owner_user_uuid' => $owner->uuid,
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'role' => TeamMember::ROLE_STAFF,
        ]);

        $this->assertSame($owner->uuid, $member->owner->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitDefaultWeekScheduleKeys(): void
    {
        $defaults = Unit::defaultWeekSchedule();
        $this->assertSame(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], array_keys($defaults));
        foreach ($defaults as $value) {
            $this->assertTrue($value);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitRelationshipsResolve(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);
        Booking::factory()->create(['user_uuid' => $owner->uuid, 'unit_uuid' => $unit->uuid]);
        UnitDateBlock::create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDay()->toDateString(),
        ]);
        UnitRateInterval::factory()->create(['user_uuid' => $owner->uuid, 'unit_uuid' => $unit->uuid]);
        UnitDiscount::create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'discount_type' => UnitDiscount::TYPE_EARLY_BIRD,
            'discount_percent' => 10,
            'status' => UnitDiscount::STATUS_ACTIVE,
        ]);

        $unit->refresh();
        $this->assertCount(1, $unit->bookings);
        $this->assertCount(1, $unit->dateBlocks);
        $this->assertCount(1, $unit->rateIntervals);
        $this->assertCount(1, $unit->discounts);
        $this->assertSame($property->uuid, $unit->property->uuid);
        $this->assertSame($owner->uuid, $unit->user->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPropertyRelationshipsResolve(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);

        $this->assertCount(1, $property->units);
        $this->assertSame($owner->uuid, $property->user->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookingPaymentRelationshipsResolve(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);
        $booking = Booking::factory()->create(['user_uuid' => $owner->uuid, 'unit_uuid' => $unit->uuid]);

        $payment = BookingPayment::factory()->create([
            'booking_uuid' => $booking->uuid,
            'user_uuid' => $owner->uuid,
        ]);

        $this->assertSame($booking->uuid, $payment->booking->uuid);
        $this->assertSame($owner->uuid, $payment->user->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitRateIntervalRelationshipsResolve(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);

        $interval = UnitRateInterval::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
        ]);

        $this->assertSame($owner->uuid, $interval->user->uuid);
        $this->assertSame($unit->uuid, $interval->unit->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookingPaymentScopeFiltersBySingleBookingUuid(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);
        $booking = Booking::factory()->create(['user_uuid' => $owner->uuid, 'unit_uuid' => $unit->uuid]);
        $other = Booking::factory()->create(['user_uuid' => $owner->uuid, 'unit_uuid' => $unit->uuid]);

        BookingPayment::factory()->create([
            'booking_uuid' => $booking->uuid,
            'user_uuid' => $owner->uuid,
        ]);
        BookingPayment::factory()->create([
            'booking_uuid' => $other->uuid,
            'user_uuid' => $owner->uuid,
        ]);

        $rows = BookingPayment::query()
            ->filters(['booking_uuid' => $booking->uuid])
            ->get();

        $this->assertCount(1, $rows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPromoCodeConstantsExposeMaps(): void
    {
        $this->assertContains(PromoCode::TYPE_FIXED, PromoCode::TYPES);
        $this->assertContains(PromoCode::TYPE_PERCENTAGE, PromoCode::TYPES);
        $this->assertContains(PromoCode::STATUS_ACTIVE, PromoCode::STATUSES);
        $this->assertContains(PromoCode::STATUS_INACTIVE, PromoCode::STATUSES);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookingSourceAndStatusMaps(): void
    {
        $this->assertNotEmpty(Booking::STATUSES);
        $this->assertContains(Booking::STATUS_PENDING, Booking::STATUSES);
        $this->assertNotSame(Booking::SOURCE_DIRECT_PORTAL, Booking::SOURCE_MANUAL);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookingRelationshipsResolve(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(['user_uuid' => $owner->uuid, 'property_uuid' => $property->uuid]);
        $booking = Booking::factory()->create(['user_uuid' => $owner->uuid, 'unit_uuid' => $unit->uuid]);
        BookingPayment::factory()->create(['booking_uuid' => $booking->uuid, 'user_uuid' => $owner->uuid]);

        $this->assertSame($owner->uuid, $booking->user->uuid);
        $this->assertSame($unit->uuid, $booking->unit->uuid);
        $this->assertCount(1, $booking->payments);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookingPaymentTypesAndKinds(): void
    {
        $this->assertContains(BookingPayment::TYPE_CASH, BookingPayment::TYPES);
        $this->assertContains(BookingPayment::KIND_PAYMENT, BookingPayment::KINDS);
        $this->assertContains(BookingPayment::KIND_REFUND, BookingPayment::KINDS);
    }
}
