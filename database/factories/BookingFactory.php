<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = Carbon::now()->addDays(fake()->numberBetween(1, 60));
        $checkOut = $checkIn->copy()->addDays(fake()->numberBetween(1, 14));

        return [
            'user_uuid' => User::factory(),
            'unit_uuid' => Unit::factory(),
            'reference' => 'HFR-'.Str::upper(Str::random(8)),
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'guest_phone' => fake()->phoneNumber(),
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'adults' => fake()->numberBetween(1, 4),
            'children' => fake()->numberBetween(0, 2),
            'total_price' => fake()->randomFloat(2, 1000, 50000),
            'currency' => 'PHP',
            'source' => Booking::SOURCE_MANUAL,
            'status' => Booking::STATUS_PENDING,
            'notes' => null,
            'portal_batch_id' => null,
        ];
    }
}
