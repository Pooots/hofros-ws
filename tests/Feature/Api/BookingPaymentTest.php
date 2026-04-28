<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Property;
use App\Models\Unit;

class BookingPaymentTest extends ApiTestCase
{
    private function makeBooking(array $bookingOverrides = []): Booking
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'currency' => 'PHP',
        ]);

        return Booking::factory()->create(array_merge([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'total_price' => 5000,
            'currency' => 'PHP',
        ], $bookingOverrides));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexReturnsPaymentsWithSummary(): void
    {
        $booking = $this->makeBooking();

        BookingPayment::factory()->count(2)->create([
            'booking_uuid' => $booking->uuid,
            'user_uuid' => $this->authUser->uuid,
            'amount' => 1000,
            'transaction_kind' => BookingPayment::KIND_PAYMENT,
        ]);

        $this->getJson('/api/v1/bookings/'.$booking->uuid.'/payments')
            ->assertOk()
            ->assertJsonCount(2, 'payments')
            ->assertJsonStructure([
                'summary',
                'payments' => [['uuid', 'amount', 'currency', 'paymentType']],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndex404WhenNotOwner(): void
    {
        $this->authenticate();
        $foreign = Booking::factory()->create();

        $this->getJson('/api/v1/bookings/'.$foreign->uuid.'/payments')
            ->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreCreatesPayment(): void
    {
        $booking = $this->makeBooking();

        $this->postJson('/api/v1/bookings/'.$booking->uuid.'/payments', [
            'amount' => 1500,
            'paymentType' => BookingPayment::TYPE_CASH,
            'notes' => 'Cash deposit',
        ])
            ->assertCreated()
            ->assertJsonPath('payment.amount', 1500)
            ->assertJsonPath('payment.paymentType', BookingPayment::TYPE_CASH)
            ->assertJsonPath('payment.transactionKind', BookingPayment::KIND_PAYMENT);

        $this->assertDatabaseHas('booking_payments', [
            'booking_uuid' => $booking->uuid,
            'amount' => 1500,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreValidatesPayload(): void
    {
        $booking = $this->makeBooking();

        $this->postJson('/api/v1/bookings/'.$booking->uuid.'/payments', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'paymentType']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreRejectsInvalidPaymentType(): void
    {
        $booking = $this->makeBooking();

        $this->postJson('/api/v1/bookings/'.$booking->uuid.'/payments', [
            'amount' => 100,
            'paymentType' => 'crypto',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['paymentType']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreRejectsRefundExceedingPaid(): void
    {
        $booking = $this->makeBooking();
        BookingPayment::factory()->create([
            'booking_uuid' => $booking->uuid,
            'user_uuid' => $this->authUser->uuid,
            'amount' => 500,
            'transaction_kind' => BookingPayment::KIND_PAYMENT,
        ]);

        $this->postJson('/api/v1/bookings/'.$booking->uuid.'/payments', [
            'amount' => 1000,
            'paymentType' => BookingPayment::TYPE_CASH,
            'transactionKind' => BookingPayment::KIND_REFUND,
        ])->assertStatus(422);
    }
}
