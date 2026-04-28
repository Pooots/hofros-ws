<?php

namespace Tests\Feature\Api;

use App\Models\Property;
use App\Models\User;

class PropertyTest extends ApiTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexReturnsOnlyOwnedProperties(): void
    {
        $owner = $this->authenticate();
        Property::factory()->count(2)->create(['user_uuid' => $owner->uuid]);
        Property::factory()->create(['user_uuid' => User::factory()->create()->uuid]);

        $this->getJson('/api/v1/configuration/properties')
            ->assertOk()
            ->assertJsonCount(2, 'properties')
            ->assertJsonStructure([
                'properties' => [
                    ['uuid', 'propertyName', 'contactEmail', 'currency', 'checkInTime', 'checkOutTime'],
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexSupportsSearchKeyword(): void
    {
        $owner = $this->authenticate();
        Property::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_name' => 'Beachfront Villa',
        ]);
        Property::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_name' => 'Downtown Loft',
        ]);

        $response = $this->getJson('/api/v1/configuration/properties?q=Beachfront')
            ->assertOk();

        $names = collect($response->json('properties'))->pluck('propertyName')->all();
        $this->assertContains('Beachfront Villa', $names);
        $this->assertNotContains('Downtown Loft', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreCreatesProperty(): void
    {
        $this->authenticate();

        $payload = [
            'propertyName' => 'Sunset Resort',
            'contactEmail' => 'info@sunset.test',
            'phone' => '+639170000000',
            'address' => 'Beach Road 1',
            'currency' => 'PHP',
            'checkInTime' => '15:00',
            'checkOutTime' => '11:00',
        ];

        $this->postJson('/api/v1/configuration/properties', $payload)
            ->assertCreated()
            ->assertJsonPath('data.propertyName', 'Sunset Resort')
            ->assertJsonPath('data.contactEmail', 'info@sunset.test');

        $this->assertDatabaseHas('properties', [
            'property_name' => 'Sunset Resort',
            'user_uuid' => $this->authUser->uuid,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreValidatesPayload(): void
    {
        $this->authenticate();

        $this->postJson('/api/v1/configuration/properties', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'propertyName', 'contactEmail', 'currency', 'checkInTime', 'checkOutTime',
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateModifiesOwnedProperty(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_name' => 'Old Name',
        ]);

        $payload = [
            'propertyName' => 'New Name',
            'contactEmail' => 'new@example.com',
            'currency' => 'PHP',
            'checkInTime' => '14:00',
            'checkOutTime' => '11:00',
        ];

        $this->putJson('/api/v1/configuration/properties/'.$property->uuid, $payload)
            ->assertOk()
            ->assertJsonPath('data.propertyName', 'New Name');

        $this->assertDatabaseHas('properties', [
            'uuid' => $property->uuid,
            'property_name' => 'New Name',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateReturns404ForOtherUsersProperty(): void
    {
        $this->authenticate();
        $other = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $other->uuid]);

        $this->putJson('/api/v1/configuration/properties/'.$property->uuid, [
            'propertyName' => 'Hacked',
            'contactEmail' => 'h@x.com',
            'currency' => 'PHP',
            'checkInTime' => '14:00',
            'checkOutTime' => '11:00',
        ])->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDestroyDeletesOwnedProperty(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);

        $this->deleteJson('/api/v1/configuration/properties/'.$property->uuid)
            ->assertOk();

        $this->assertDatabaseMissing('properties', ['uuid' => $property->uuid]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDestroyBlocksDeletingOtherUsersProperty(): void
    {
        $this->authenticate();
        $other = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $other->uuid]);

        $this->deleteJson('/api/v1/configuration/properties/'.$property->uuid)
            ->assertStatus(404);

        $this->assertDatabaseHas('properties', ['uuid' => $property->uuid]);
    }
}
