<?php

namespace Tests\Feature\Api;

use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitDiscount;

class UnitDiscountTest extends ApiTestCase
{
    private function setupUnit(): Unit
    {
        $owner = $this->authenticate();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);

        return Unit::factory()->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexReturnsOnlyOwnedDiscounts(): void
    {
        $unit = $this->setupUnit();
        UnitDiscount::factory()->count(2)->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
        ]);
        UnitDiscount::factory()->create();

        $this->getJson('/api/v1/discounts/unit-discounts')
            ->assertOk()
            ->assertJsonCount(2, 'unit_discounts');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreCreatesUnitDiscount(): void
    {
        $unit = $this->setupUnit();

        $this->postJson('/api/v1/discounts/unit-discounts', [
            'unit_uuid' => $unit->uuid,
            'discount_type' => UnitDiscount::TYPE_LONG_STAY,
            'discount_percent' => 10,
            'min_nights' => 5,
            'status' => UnitDiscount::STATUS_ACTIVE,
        ])
            ->assertCreated()
            ->assertJsonPath('data.discount_type', UnitDiscount::TYPE_LONG_STAY)
            ->assertJsonPath('data.discount_percent', 10);

        $this->assertDatabaseHas('unit_discounts', [
            'unit_uuid' => $unit->uuid,
            'discount_type' => UnitDiscount::TYPE_LONG_STAY,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreValidatesRequiredFields(): void
    {
        $this->setupUnit();

        $this->postJson('/api/v1/discounts/unit-discounts', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit_uuid', 'discount_type', 'discount_percent']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateModifiesDiscount(): void
    {
        $unit = $this->setupUnit();
        $discount = UnitDiscount::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
        ]);

        $this->putJson('/api/v1/discounts/unit-discounts/'.$discount->uuid, [
            'discount_percent' => 25,
        ])
            ->assertOk()
            ->assertJsonPath('data.discount_percent', 25);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDestroyRemovesDiscount(): void
    {
        $unit = $this->setupUnit();
        $discount = UnitDiscount::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
        ]);

        $this->deleteJson('/api/v1/discounts/unit-discounts/'.$discount->uuid)
            ->assertOk()
            ->assertJson(['message' => 'Unit discount deleted.']);

        $this->assertDatabaseMissing('unit_discounts', ['uuid' => $discount->uuid]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateRejectsInvertedValidityWindow(): void
    {
        $unit = $this->setupUnit();
        $discount = UnitDiscount::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
        ]);

        $this->putJson('/api/v1/discounts/unit-discounts/'.$discount->uuid, [
            'valid_from' => '2026-06-01',
            'valid_to' => '2026-05-01',
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateRejectsValidToBeforeStoredValidFrom(): void
    {
        $unit = $this->setupUnit();
        $discount = UnitDiscount::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'valid_from' => '2026-08-01',
            'valid_to' => '2026-12-31',
        ]);

        $this->putJson('/api/v1/discounts/unit-discounts/'.$discount->uuid, [
            'valid_to' => '2026-07-01',
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateAcceptsCompleteWindow(): void
    {
        $unit = $this->setupUnit();
        $discount = UnitDiscount::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'valid_from' => null,
            'valid_to' => null,
        ]);

        $this->putJson('/api/v1/discounts/unit-discounts/'.$discount->uuid, [
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-06-30',
            'notes' => 'Updated window',
        ])
            ->assertOk()
            ->assertJsonPath('data.valid_from', '2026-01-01');
    }
}
