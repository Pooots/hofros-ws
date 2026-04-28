<?php

namespace Tests\Feature\Api;

class NotificationSettingsTest extends ApiTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testShowReturnsDefaultPreferencesForNewUser(): void
    {
        $this->authenticate();

        $this->getJson('/api/v1/configuration/notifications')
            ->assertOk()
            ->assertJson([
                'newBooking' => true,
                'cancellation' => true,
                'checkIn' => true,
                'checkOut' => false,
                'payment' => true,
                'review' => false,
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdatePersistsPreferences(): void
    {
        $owner = $this->authenticate();

        $this->putJson('/api/v1/configuration/notifications', [
            'newBooking' => false,
            'cancellation' => true,
            'checkIn' => false,
            'checkOut' => true,
            'payment' => false,
            'review' => true,
        ])
            ->assertOk()
            ->assertJson([
                'newBooking' => false,
                'cancellation' => true,
                'checkIn' => false,
                'checkOut' => true,
                'payment' => false,
                'review' => true,
            ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_uuid' => $owner->uuid,
            'new_booking' => false,
            'check_out' => true,
            'review' => true,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateValidatesRequiredFields(): void
    {
        $this->authenticate();

        $this->putJson('/api/v1/configuration/notifications', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'newBooking', 'cancellation', 'checkIn', 'checkOut', 'payment', 'review',
            ]);
    }
}
