<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'property_uuid' => Property::factory(),
            'name' => fake()->words(2, true),
            'details' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'images' => [],
            'type' => fake()->randomElement(['Studio', 'Apartment', 'Villa', 'Room']),
            'max_guests' => fake()->numberBetween(1, 8),
            'bedrooms' => fake()->numberBetween(1, 4),
            'beds' => fake()->numberBetween(1, 4),
            'price_per_night' => fake()->randomFloat(2, 1000, 10000),
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
            'week_schedule' => Unit::defaultWeekSchedule(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => Unit::STATUS_INACTIVE]);
    }
}
