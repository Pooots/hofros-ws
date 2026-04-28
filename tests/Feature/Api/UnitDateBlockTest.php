<?php

namespace Tests\Feature\Api;

use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use Illuminate\Support\Carbon;

class UnitDateBlockTest extends ApiTestCase
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
    public function testIndexListsBlocksForUnit(): void
    {
        $unit = $this->setupUnit();
        UnitDateBlock::factory()->count(2)->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
        ]);
        UnitDateBlock::factory()->create();

        $this->getJson('/api/v1/unit-date-blocks?unit_uuid='.$unit->uuid)
            ->assertOk()
            ->assertJsonCount(2, 'blocks');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreCreatesABlock(): void
    {
        $unit = $this->setupUnit();
        $start = Carbon::now()->addDays(10)->toDateString();
        $end = Carbon::now()->addDays(15)->toDateString();

        $this->postJson('/api/v1/unit-date-blocks', [
            'unit_uuid' => $unit->uuid,
            'startDate' => $start,
            'endDate' => $end,
            'label' => 'Maintenance',
            'notes' => 'HVAC repair',
        ])
            ->assertCreated()
            ->assertJsonPath('data.label', 'Maintenance');

        $this->assertDatabaseHas('unit_date_blocks', [
            'unit_uuid' => $unit->uuid,
            'label' => 'Maintenance',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreRejectsOverlappingBlock(): void
    {
        $unit = $this->setupUnit();
        UnitDateBlock::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::now()->addDays(5)->toDateString(),
            'end_date' => Carbon::now()->addDays(10)->toDateString(),
        ]);

        $this->postJson('/api/v1/unit-date-blocks', [
            'unit_uuid' => $unit->uuid,
            'startDate' => Carbon::now()->addDays(7)->toDateString(),
            'endDate' => Carbon::now()->addDays(12)->toDateString(),
            'label' => 'Overlap',
        ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateModifiesBlock(): void
    {
        $unit = $this->setupUnit();
        $block = UnitDateBlock::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::now()->addDays(20)->toDateString(),
            'end_date' => Carbon::now()->addDays(22)->toDateString(),
        ]);

        $this->patchJson('/api/v1/unit-date-blocks/'.$block->uuid, [
            'label' => 'Updated Label',
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Updated Label');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDestroyRemovesBlock(): void
    {
        $unit = $this->setupUnit();
        $block = UnitDateBlock::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
        ]);

        $this->deleteJson('/api/v1/unit-date-blocks/'.$block->uuid)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('unit_date_blocks', ['uuid' => $block->uuid]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testValidationRequiresFields(): void
    {
        $this->setupUnit();

        $this->postJson('/api/v1/unit-date-blocks', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit_uuid', 'startDate', 'endDate', 'label']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreRejectsWhenOverlappingFirmBooking(): void
    {
        $unit = $this->setupUnit();
        \App\Models\Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::now()->addDays(10)->toDateString(),
            'check_out' => Carbon::now()->addDays(13)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'status' => \App\Models\Booking::STATUS_ASSIGNED,
        ]);

        $this->postJson('/api/v1/unit-date-blocks', [
            'unit_uuid' => $unit->uuid,
            'startDate' => Carbon::now()->addDays(11)->toDateString(),
            'endDate' => Carbon::now()->addDays(12)->toDateString(),
            'label' => 'Repair',
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateRejectsInvertedDates(): void
    {
        $unit = $this->setupUnit();
        $block = UnitDateBlock::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::now()->addDays(15)->toDateString(),
            'end_date' => Carbon::now()->addDays(17)->toDateString(),
        ]);

        $this->patchJson('/api/v1/unit-date-blocks/'.$block->uuid, [
            'startDate' => Carbon::now()->addDays(20)->toDateString(),
            'endDate' => Carbon::now()->addDays(20)->toDateString(),
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateRejectsOverlappingExistingBlock(): void
    {
        $unit = $this->setupUnit();
        UnitDateBlock::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::now()->addDays(5)->toDateString(),
            'end_date' => Carbon::now()->addDays(8)->toDateString(),
        ]);

        $block = UnitDateBlock::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::now()->addDays(20)->toDateString(),
            'end_date' => Carbon::now()->addDays(25)->toDateString(),
        ]);

        $this->patchJson('/api/v1/unit-date-blocks/'.$block->uuid, [
            'startDate' => Carbon::now()->addDays(6)->toDateString(),
            'endDate' => Carbon::now()->addDays(9)->toDateString(),
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateSupportsChangingUnitLabelAndNotes(): void
    {
        $unit = $this->setupUnit();
        $unit2 = Unit::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'property_uuid' => $unit->property_uuid,
        ]);
        $block = UnitDateBlock::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::now()->addDays(40)->toDateString(),
            'end_date' => Carbon::now()->addDays(43)->toDateString(),
        ]);

        $this->patchJson('/api/v1/unit-date-blocks/'.$block->uuid, [
            'unit_uuid' => $unit2->uuid,
            'startDate' => Carbon::now()->addDays(45)->toDateString(),
            'endDate' => Carbon::now()->addDays(48)->toDateString(),
            'label' => 'Reassigned',
            'notes' => 'Different unit',
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Reassigned');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateRejectsWhenOverlapsFirmBooking(): void
    {
        $unit = $this->setupUnit();
        $block = UnitDateBlock::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'start_date' => Carbon::now()->addDays(60)->toDateString(),
            'end_date' => Carbon::now()->addDays(62)->toDateString(),
        ]);
        \App\Models\Booking::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
            'check_in' => Carbon::now()->addDays(70)->toDateString(),
            'check_out' => Carbon::now()->addDays(73)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'status' => \App\Models\Booking::STATUS_ASSIGNED,
        ]);

        $this->patchJson('/api/v1/unit-date-blocks/'.$block->uuid, [
            'startDate' => Carbon::now()->addDays(71)->toDateString(),
            'endDate' => Carbon::now()->addDays(72)->toDateString(),
        ])->assertStatus(422);
    }
}
