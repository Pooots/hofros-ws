<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\UnitDiscount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitDiscount>
 */
class UnitDiscountFactory extends Factory
{
    protected $model = UnitDiscount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'unit_uuid' => Unit::factory(),
            'discount_type' => UnitDiscount::TYPE_LONG_STAY,
            'discount_percent' => fake()->randomFloat(2, 5, 30),
            'min_days_in_advance' => null,
            'min_nights' => fake()->numberBetween(2, 7),
            'valid_from' => null,
            'valid_to' => null,
            'status' => UnitDiscount::STATUS_ACTIVE,
        ];
    }
}
