<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\UnitDateBlock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<UnitDateBlock>
 */
class UnitDateBlockFactory extends Factory
{
    protected $model = UnitDateBlock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::now()->addDays(fake()->numberBetween(1, 30));
        $end = $start->copy()->addDays(fake()->numberBetween(1, 5));

        return [
            'user_uuid' => User::factory(),
            'unit_uuid' => Unit::factory(),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'label' => fake()->randomElement(['Maintenance', 'Owner Stay', 'Renovation', 'Holiday']),
            'notes' => fake()->sentence(),
        ];
    }
}
