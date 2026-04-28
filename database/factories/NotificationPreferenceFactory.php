<?php

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'new_booking' => true,
            'cancellation' => true,
            'check_in' => true,
            'check_out' => false,
            'payment' => true,
            'review' => false,
        ];
    }
}
