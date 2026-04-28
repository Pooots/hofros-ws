<?php

namespace Tests\Unit\Support;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Support\BookingPaymentSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPaymentSummaryTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function testComputeReturnsBalanceDueWhenUnderpaid(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);
        $booking = Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'total_price' => 5000,
            'currency' => 'PHP',
        ]);

        $payments = collect([
            BookingPayment::factory()->create([
                'booking_uuid' => $booking->uuid,
                'user_uuid' => $owner->uuid,
                'amount' => 1500,
                'transaction_kind' => BookingPayment::KIND_PAYMENT,
            ]),
        ]);

        $summary = BookingPaymentSummary::compute($booking, [$booking->uuid], $payments);

        $this->assertSame(5000.0, $summary['grandTotal']);
        $this->assertSame(1500.0, $summary['totalPaid']);
        $this->assertSame(0.0, $summary['totalRefunded']);
        $this->assertSame(1500.0, $summary['netPaid']);
        $this->assertSame(3500.0, $summary['balanceDue']);
        $this->assertSame(0.0, $summary['overpaid']);
        $this->assertSame('PHP', $summary['currency']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testComputeHandlesRefundsAndOverpayment(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);
        $booking = Booking::factory()->create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $unit->uuid,
            'total_price' => 3000,
            'currency' => 'PHP',
        ]);

        $payments = collect([
            BookingPayment::factory()->create([
                'booking_uuid' => $booking->uuid,
                'user_uuid' => $owner->uuid,
                'amount' => 4000,
                'transaction_kind' => BookingPayment::KIND_PAYMENT,
            ]),
            BookingPayment::factory()->create([
                'booking_uuid' => $booking->uuid,
                'user_uuid' => $owner->uuid,
                'amount' => 200,
                'transaction_kind' => BookingPayment::KIND_REFUND,
            ]),
        ]);

        $summary = BookingPaymentSummary::compute($booking, [$booking->uuid], $payments);

        $this->assertSame(3800.0, $summary['netPaid']);
        $this->assertSame(800.0, $summary['overpaid']);
        $this->assertSame(0.0, $summary['balanceDue']);
        $this->assertSame(3800.0, $summary['refundableAmount']);
    }
}
