<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPayment>
 */
class BookingPaymentFactory extends Factory
{
    protected $model = BookingPayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_uuid' => Booking::factory(),
            'user_uuid' => User::factory(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'currency' => 'PHP',
            'payment_type' => BookingPayment::TYPE_CASH,
            'transaction_kind' => BookingPayment::KIND_PAYMENT,
            'notes' => null,
        ];
    }
}
