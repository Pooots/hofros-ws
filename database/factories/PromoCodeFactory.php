<?php

namespace Database\Factories;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'code' => Str::upper(Str::random(8)),
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => fake()->randomFloat(2, 5, 50),
            'min_nights' => 1,
            'max_uses' => fake()->numberBetween(10, 100),
            'uses_count' => 0,
            'status' => PromoCode::STATUS_ACTIVE,
        ];
    }
}
