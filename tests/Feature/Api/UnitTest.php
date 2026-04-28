<?php

namespace Tests\Feature\Api;

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;

class UnitTest extends ApiTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexReturnsOnlyOwnedUnits(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        Unit::factory()->count(3)->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);
        Unit::factory()->create();

        $this->getJson('/api/v1/configuration/units')
            ->assertOk()
            ->assertJsonCount(3, 'units');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreCreatesUnit(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);

        $payload = [
            'propertyId' => $property->uuid,
            'name' => 'Suite A',
            'details' => 'A nice suite',
            'description' => 'Spacious room',
            'type' => 'Suite',
            'maxGuests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'pricePerNight' => 2500,
            'currency' => 'PHP',
            'status' => Unit::STATUS_ACTIVE,
        ];

        $this->postJson('/api/v1/configuration/units', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Suite A')
            ->assertJsonPath('data.propertyId', $property->uuid)
            ->assertJsonPath('data.status', Unit::STATUS_ACTIVE);

        $this->assertDatabaseHas('units', [
            'name' => 'Suite A',
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreValidatesRequiredFields(): void
    {
        $this->authenticate();

        $this->postJson('/api/v1/configuration/units', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['propertyId', 'name', 'maxGuests', 'bedrooms', 'beds', 'status']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreRejectsPropertyOwnedByAnotherUser(): void
    {
        $this->authenticate();
        $other = User::factory()->create();
        $foreignProperty = Property::factory()->create(['user_uuid' => $other->uuid]);

        $this->postJson('/api/v1/configuration/units', [
            'propertyId' => $foreignProperty->uuid,
            'name' => 'Hack',
            'maxGuests' => 1,
            'bedrooms' => 1,
            'beds' => 1,
            'status' => Unit::STATUS_ACTIVE,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['propertyId']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateModifiesOwnedUnit(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);

        $this->putJson('/api/v1/configuration/units/'.$unit->uuid, [
            'propertyId' => $property->uuid,
            'name' => 'Updated Name',
            'maxGuests' => 4,
            'bedrooms' => 2,
            'beds' => 2,
            'pricePerNight' => 3000,
            'currency' => 'PHP',
            'status' => Unit::STATUS_INACTIVE,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.status', Unit::STATUS_INACTIVE);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testWeekScheduleCanBePatched(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);

        $schedule = [
            'mon' => true, 'tue' => true, 'wed' => true,
            'thu' => false, 'fri' => true, 'sat' => true, 'sun' => false,
        ];

        $this->patchJson('/api/v1/configuration/units/'.$unit->uuid.'/week-schedule', [
            'weekSchedule' => $schedule,
        ])
            ->assertOk()
            ->assertJsonPath('data.weekSchedule.thu', false)
            ->assertJsonPath('data.weekSchedule.fri', true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDestroyDeletesOwnedUnit(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);

        $this->deleteJson('/api/v1/configuration/units/'.$unit->uuid)
            ->assertOk()
            ->assertJson(['message' => 'Unit deleted.']);

        $this->assertDatabaseMissing('units', ['uuid' => $unit->uuid]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testOtherUserUnitCannotBeUpdated(): void
    {
        $owner = $this->authenticate();
        $myProperty = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $foreign = Unit::factory()->create();

        $this->putJson('/api/v1/configuration/units/'.$foreign->uuid, [
            'propertyId' => $myProperty->uuid,
            'name' => 'Hacked',
            'status' => Unit::STATUS_ACTIVE,
        ])->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateUsesPropertyCurrencyWhenCurrencyOmitted(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create([
            'user_uuid' => $owner->uuid,
            'currency' => 'USD',
        ]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'currency' => 'PHP',
        ]);

        $this->putJson('/api/v1/configuration/units/'.$unit->uuid, [
            'propertyId' => $property->uuid,
            'name' => 'Refreshed',
            'maxGuests' => 4,
            'bedrooms' => 1,
            'beds' => 1,
            'pricePerNight' => 1500,
            'currency' => '',
            'status' => Unit::STATUS_ACTIVE,
        ])
            ->assertOk()
            ->assertJsonPath('data.currency', 'USD');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUploadImagesAppendsImagePaths(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'images' => ['/storage/units/old.png'],
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('photo.jpg', 800, 600);

        $this->postJson('/api/v1/configuration/units/'.$unit->uuid.'/images', [
            'images' => [$file],
        ])
            ->assertOk()
            ->assertJsonPath('data.images.0', '/storage/units/old.png')
            ->assertJsonStructure(['data' => ['images']]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUploadImagesStopsAt20Total(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $existing = array_map(static fn (int $i): string => "/storage/units/old-{$i}.png", range(1, 18));
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'images' => $existing,
        ]);

        $files = array_map(
            static fn (int $i) => \Illuminate\Http\UploadedFile::fake()->image("new-{$i}.jpg", 400, 300),
            range(1, 5),
        );

        $response = $this->postJson('/api/v1/configuration/units/'.$unit->uuid.'/images', [
            'images' => $files,
        ])->assertOk();

        $images = $response->json('data.images');
        $this->assertCount(20, $images);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUploadImagesValidatesRequired(): void
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);

        $this->postJson('/api/v1/configuration/units/'.$unit->uuid.'/images', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['images']);
    }
}
