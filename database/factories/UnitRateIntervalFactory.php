<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\UnitRateInterval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<UnitRateInterval>
 */
class UnitRateIntervalFactory extends Factory
{
    protected $model = UnitRateInterval::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::now()->addDays(fake()->numberBetween(1, 30));
        $end = $start->copy()->addDays(fake()->numberBetween(7, 30));

        return [
            'user_uuid' => User::factory(),
            'unit_uuid' => Unit::factory(),
            'name' => fake()->randomElement(['Peak Season', 'Holiday Special', 'Weekend Rate']),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'min_los' => 1,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'day_prices' => null,
            'base_price' => fake()->randomFloat(2, 1500, 8000),
            'currency' => 'PHP',
        ];
    }
}
