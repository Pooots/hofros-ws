<?php

namespace Tests\Feature\Api;

use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitRateInterval;
use Illuminate\Support\Carbon;

class UnitRateIntervalTest extends ApiTestCase
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

    private function ratePayload(): array
    {
        return [
            'name' => 'Peak Season',
            'startDate' => Carbon::now()->addDays(10)->toDateString(),
            'endDate' => Carbon::now()->addDays(40)->toDateString(),
            'minLos' => 2,
            'maxLos' => 14,
            'closedToArrival' => false,
            'closedToDeparture' => false,
            'daysOfWeek' => [
                'sun' => true, 'mon' => true, 'tue' => true,
                'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true,
            ],
            'dayPrices' => [
                'sun' => 3000, 'mon' => 2500, 'tue' => 2500,
                'wed' => 2500, 'thu' => 2500, 'fri' => 3500, 'sat' => 4000,
            ],
            'currency' => 'PHP',
        ];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexListsIntervalsForUnit(): void
    {
        $unit = $this->setupUnit();
        UnitRateInterval::factory()->count(2)->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
        ]);

        $this->getJson("/api/v1/configuration/units/{$unit->uuid}/rate-intervals")
            ->assertOk()
            ->assertJsonCount(2, 'intervals');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreCreatesAnInterval(): void
    {
        $unit = $this->setupUnit();

        $this->postJson("/api/v1/configuration/units/{$unit->uuid}/rate-intervals", $this->ratePayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Peak Season')
            ->assertJsonPath('data.unitId', $unit->uuid)
            ->assertJsonPath('data.dayPrices.fri', 3500);

        $this->assertDatabaseHas('unit_rate_intervals', [
            'name' => 'Peak Season',
            'unit_uuid' => $unit->uuid,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreValidatesRequiredFields(): void
    {
        $unit = $this->setupUnit();

        $this->postJson("/api/v1/configuration/units/{$unit->uuid}/rate-intervals", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['startDate', 'endDate', 'daysOfWeek', 'dayPrices', 'currency']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateModifiesInterval(): void
    {
        $unit = $this->setupUnit();
        $interval = UnitRateInterval::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
        ]);

        $payload = array_merge($this->ratePayload(), ['name' => 'Renamed Interval']);

        $this->putJson("/api/v1/configuration/units/{$unit->uuid}/rate-intervals/{$interval->uuid}", $payload)
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Interval');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreRejectsMaxLosBelowMinLos(): void
    {
        $unit = $this->setupUnit();
        $payload = array_merge($this->ratePayload(), [
            'minLos' => 5,
            'maxLos' => 2,
        ]);

        $this->postJson("/api/v1/configuration/units/{$unit->uuid}/rate-intervals", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['maxLos']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreRejectsWhenNoDaysOfWeekSelected(): void
    {
        $unit = $this->setupUnit();
        $payload = array_merge($this->ratePayload(), [
            'daysOfWeek' => [
                'sun' => false, 'mon' => false, 'tue' => false,
                'wed' => false, 'thu' => false, 'fri' => false, 'sat' => false,
            ],
        ]);

        $this->postJson("/api/v1/configuration/units/{$unit->uuid}/rate-intervals", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['daysOfWeek']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDestroyRemovesInterval(): void
    {
        $unit = $this->setupUnit();
        $interval = UnitRateInterval::factory()->create([
            'user_uuid' => $this->authUser->uuid,
            'unit_uuid' => $unit->uuid,
        ]);

        $this->deleteJson("/api/v1/configuration/units/{$unit->uuid}/rate-intervals/{$interval->uuid}")
            ->assertOk()
            ->assertJson(['message' => 'Interval deleted.']);

        $this->assertDatabaseMissing('unit_rate_intervals', ['uuid' => $interval->uuid]);
    }
}
