<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\NoBookingPaymentFoundException;
use App\Http\Repositories\BookingPaymentRepository;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingPaymentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repo(): BookingPaymentRepository
    {
        return new BookingPaymentRepository(new BookingPayment());
    }

    private function makeBooking(array $bookingOverrides = []): Booking
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);

        return Booking::factory()->create(array_merge([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
        ], $bookingOverrides));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGetAllFiltersByBookingUuid(): void
    {
        $booking = $this->makeBooking();
        BookingPayment::factory()->count(2)->create([
            'booking_uuid' => $booking->uuid,
            'user_uuid' => $booking->user_uuid,
        ]);
        BookingPayment::factory()->count(3)->create();

        $rows = $this->repo()->getAll(['booking_uuid' => $booking->uuid])->get();

        $this->assertCount(2, $rows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGetAllFiltersByTransactionKind(): void
    {
        $booking = $this->makeBooking();
        BookingPayment::factory()->create([
            'booking_uuid' => $booking->uuid,
            'user_uuid' => $booking->user_uuid,
            'transaction_kind' => BookingPayment::KIND_REFUND,
        ]);
        BookingPayment::factory()->create([
            'booking_uuid' => $booking->uuid,
            'user_uuid' => $booking->user_uuid,
            'transaction_kind' => BookingPayment::KIND_PAYMENT,
        ]);

        $rows = $this->repo()
            ->getAll(['booking_uuid' => $booking->uuid, 'transaction_kind' => BookingPayment::KIND_REFUND])
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(BookingPayment::KIND_REFUND, $rows->first()->transaction_kind);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGetAllFiltersByBookingUuidsArray(): void
    {
        $a = $this->makeBooking();
        $b = $this->makeBooking();
        BookingPayment::factory()->create(['booking_uuid' => $a->uuid, 'user_uuid' => $a->user_uuid]);
        BookingPayment::factory()->create(['booking_uuid' => $b->uuid, 'user_uuid' => $b->user_uuid]);
        BookingPayment::factory()->create();

        $rows = $this->repo()->getAll(['booking_uuids' => [$a->uuid, $b->uuid]])->get();

        $this->assertCount(2, $rows);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testFetchOrThrowReturnsPayment(): void
    {
        $booking = $this->makeBooking();
        $payment = BookingPayment::factory()->create([
            'booking_uuid' => $booking->uuid,
            'user_uuid' => $booking->user_uuid,
        ]);

        $found = $this->repo()->fetchOrThrow('uuid', $payment->uuid);
        $this->assertSame($payment->uuid, $found->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testFetchOrThrowRaisesExceptionWhenMissing(): void
    {
        $this->expectException(NoBookingPaymentFoundException::class);
        $this->repo()->fetchOrThrow('uuid', (string) Str::uuid());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookingUuidsForAggregateReturnsOnlySelfWhenNoBatch(): void
    {
        $booking = $this->makeBooking(['portal_batch_id' => null]);

        $this->assertSame([$booking->uuid], $this->repo()->bookingUuidsForAggregate($booking));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookingUuidsForAggregateReturnsBatchSiblings(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);
        $batchId = (string) Str::uuid();
        $a = Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'portal_batch_id' => $batchId,
        ]);
        $b = Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'portal_batch_id' => $batchId,
        ]);

        $uuids = $this->repo()->bookingUuidsForAggregate($a);

        sort($uuids);
        $expected = [$a->uuid, $b->uuid];
        sort($expected);
        $this->assertSame($expected, $uuids);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCreateStripsUnknownAndNullFields(): void
    {
        $booking = $this->makeBooking();
        $payment = $this->repo()->create([
            'booking_uuid' => $booking->uuid,
            'user_uuid' => $booking->user_uuid,
            'amount' => 100,
            'currency' => 'PHP',
            'payment_type' => BookingPayment::TYPE_CASH,
            'transaction_kind' => BookingPayment::KIND_PAYMENT,
            'notes' => null,
            'phantom' => 'ignored',
        ]);

        $this->assertSame(100.0, (float) $payment->amount);
        $this->assertNull($payment->notes);
    }
}
