<?php

namespace Tests\Unit\Support;

use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitRateInterval;
use App\Models\User;
use App\Support\UnitStayPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UnitStayPricingTest extends TestCase
{
    use RefreshDatabase;

    private function makeUnit(array $overrides = []): Unit
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);

        return Unit::factory()->create(array_merge([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'price_per_night' => 1500,
            'currency' => 'PHP',
        ], $overrides));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testComputeForStayUsesUnitPriceWhenNoIntervals(): void
    {
        $unit = $this->makeUnit(['price_per_night' => 2000]);

        $result = UnitStayPricing::computeForStay(
            $unit,
            Carbon::today()->addDays(2),
            Carbon::today()->addDays(5)
        );

        $this->assertSame(6000.0, $result['total']);
        $this->assertSame(3, (int) $result['nights']);
        $this->assertNull($result['error']);
        $this->assertSame('PHP', $result['currency']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testComputeForStayUsesRateIntervalBasePrice(): void
    {
        $unit = $this->makeUnit(['price_per_night' => 1500]);
        $start = Carbon::today()->addDays(2);
        $end = Carbon::today()->addDays(5);

        UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'Window',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
            'min_los' => null,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => null,
            'base_price' => 2500,
            'currency' => 'PHP',
        ]);

        $result = UnitStayPricing::computeForStay($unit, $start, $end);

        $this->assertSame(7500.0, $result['total']);
        $this->assertSame(3, (int) $result['nights']);
        $this->assertNull($result['error']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testComputeForStayReturnsMinLosError(): void
    {
        $unit = $this->makeUnit();
        $start = Carbon::today()->addDays(2);
        $end = $start->copy()->addDay();

        UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'MinLos',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
            'min_los' => 3,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => null,
            'base_price' => 2500,
            'currency' => 'PHP',
        ]);

        $result = UnitStayPricing::computeForStay($unit, $start, $end);

        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Minimum stay', $result['error']);
        $this->assertSame(0.0, $result['total']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testComputeForStayReturnsMaxLosError(): void
    {
        $unit = $this->makeUnit();
        $start = Carbon::today()->addDays(2);
        $end = $start->copy()->addDays(10);

        UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'MaxLos',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
            'min_los' => null,
            'max_los' => 5,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => null,
            'base_price' => 2500,
            'currency' => 'PHP',
        ]);

        $result = UnitStayPricing::computeForStay($unit, $start, $end);

        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Maximum stay', $result['error']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testComputeForStayReturnsClosedToArrivalError(): void
    {
        $unit = $this->makeUnit();
        $start = Carbon::today()->addDays(2);
        $end = $start->copy()->addDays(2);

        UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'CTA',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
            'min_los' => null,
            'max_los' => null,
            'closed_to_arrival' => true,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => null,
            'base_price' => 2500,
            'currency' => 'PHP',
        ]);

        $result = UnitStayPricing::computeForStay($unit, $start, $end);

        $this->assertStringContainsString('Arrival is not allowed', $result['error']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDisplayMinMaxForPortalFallsBackToUnitPrice(): void
    {
        $unit = $this->makeUnit(['price_per_night' => 1750]);

        $minMax = UnitStayPricing::displayMinMaxNightlyForPortal($unit);

        $this->assertSame(1750.0, $minMax['min']);
        $this->assertSame(1750.0, $minMax['max']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDisplayMinMaxUsesIntervalsWhenPresent(): void
    {
        $unit = $this->makeUnit(['price_per_night' => 1500]);

        UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'Range',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
            'min_los' => null,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'fri' => true, 'sat' => true],
            'day_prices' => ['fri' => 5000, 'sat' => 6000],
            'base_price' => 2000,
            'currency' => 'PHP',
        ]);

        $minMax = UnitStayPricing::displayMinMaxNightlyForPortal($unit->fresh());

        $this->assertSame(2000.0, $minMax['min']);
        $this->assertSame(6000.0, $minMax['max']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDisplayMinForPortalReturnsMin(): void
    {
        $unit = $this->makeUnit(['price_per_night' => 1500]);

        $this->assertSame(1500.0, UnitStayPricing::displayMinNightlyForPortal($unit));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testComputeForStayReturnsClosedToDepartureError(): void
    {
        $unit = $this->makeUnit();
        $start = Carbon::today()->addDays(3);
        $end = $start->copy()->addDays(2);

        UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'CTD',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
            'min_los' => null,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => true,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => null,
            'base_price' => 2500,
            'currency' => 'PHP',
        ]);

        $result = UnitStayPricing::computeForStay($unit, $start, $end);

        $this->assertStringContainsString('Departure is not allowed', $result['error']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDisplayMinMaxIgnoresPastIntervals(): void
    {
        $unit = $this->makeUnit(['price_per_night' => 1500]);

        UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'Past',
            'start_date' => Carbon::today()->subDays(60)->toDateString(),
            'end_date' => Carbon::today()->subDays(30)->toDateString(),
            'min_los' => null,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true],
            'day_prices' => null,
            'base_price' => 9999,
            'currency' => 'PHP',
        ]);

        $minMax = UnitStayPricing::displayMinMaxNightlyForPortal($unit->fresh());

        $this->assertSame(1500.0, $minMax['min']);
        $this->assertSame(1500.0, $minMax['max']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDisplayMinMaxIgnoresIntervalsWithNoActiveDays(): void
    {
        $unit = $this->makeUnit(['price_per_night' => 1500]);

        UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'NoDays',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
            'min_los' => null,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => false, 'tue' => false, 'wed' => false, 'thu' => false, 'fri' => false, 'sat' => false, 'sun' => false],
            'day_prices' => null,
            'base_price' => 9999,
            'currency' => 'PHP',
        ]);

        $minMax = UnitStayPricing::displayMinMaxNightlyForPortal($unit->fresh());

        $this->assertSame(1500.0, $minMax['min']);
        $this->assertSame(1500.0, $minMax['max']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBestIntervalPrefersNarrowerSpanAndNewerCreatedAt(): void
    {
        $unit = $this->makeUnit(['price_per_night' => 1500]);
        $start = Carbon::today()->addDays(3);
        $end = $start->copy()->addDays(2);

        UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'Wide window',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(60)->toDateString(),
            'min_los' => null,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => null,
            'base_price' => 1000,
            'currency' => 'PHP',
        ]);

        UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'Narrow window',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'min_los' => null,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => null,
            'base_price' => 5000,
            'currency' => 'PHP',
        ]);

        $result = UnitStayPricing::computeForStay($unit->fresh(), $start, $end);

        $this->assertNull($result['error']);
        $this->assertSame(10000.0, $result['total']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBestIntervalSkipsWhenNightFallsOutsideRange(): void
    {
        $unit = $this->makeUnit(['price_per_night' => 1500]);
        $start = Carbon::today()->addDays(2);
        $end = $start->copy()->addDays(3);

        UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'Only first night',
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
            'min_los' => null,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => null,
            'base_price' => 4000,
            'currency' => 'PHP',
        ]);

        $result = UnitStayPricing::computeForStay($unit->fresh(), $start, $end);

        $this->assertNull($result['error']);
        $this->assertSame(4000.0 + 1500.0 + 1500.0, $result['total']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDisplayMinMaxBreaksTieByCreatedAt(): void
    {
        $unit = $this->makeUnit(['price_per_night' => 1500]);

        $older = UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'Tie A',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(10)->toDateString(),
            'min_los' => null,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => null,
            'base_price' => 2000,
            'currency' => 'PHP',
        ]);
        $older->forceFill([
            'created_at' => Carbon::now()->subDays(2),
            'updated_at' => Carbon::now()->subDays(2),
        ])->save();

        $newer = UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'Tie B',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(10)->toDateString(),
            'min_los' => null,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => null,
            'base_price' => 3500,
            'currency' => 'PHP',
        ]);
        $newer->forceFill([
            'created_at' => Carbon::now()->addSeconds(5),
            'updated_at' => Carbon::now()->addSeconds(5),
        ])->save();

        $result = UnitStayPricing::computeForStay(
            $unit->fresh(),
            Carbon::today()->addDays(2),
            Carbon::today()->addDays(4)
        );

        $this->assertNull($result['error']);
        $this->assertSame(7000.0, $result['total']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDisplayMinMaxSkipsIntervalsWithNullDates(): void
    {
        $unit = $this->makeUnit(['price_per_night' => 1500]);
        $real = UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'Real',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(5)->toDateString(),
            'min_los' => null,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => null,
            'base_price' => 2200,
            'currency' => 'PHP',
        ]);

        $ghost = new UnitRateInterval($real->getAttributes());
        $ghost->setRawAttributes(array_merge($real->getAttributes(), [
            'start_date' => null,
            'end_date' => null,
        ]));

        $unit->setRelation('rateIntervals', collect([$ghost, $real]));

        $minMax = UnitStayPricing::displayMinMaxNightlyForPortal($unit);

        $this->assertSame(2200.0, $minMax['min']);
        $this->assertSame(2200.0, $minMax['max']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testComputeForStaySkipsIntervalsWithNullDates(): void
    {
        $unit = $this->makeUnit(['price_per_night' => 1500]);
        $start = Carbon::today()->addDays(2);
        $end = $start->copy()->addDays(2);

        $real = UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'Real',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
            'min_los' => null,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => null,
            'base_price' => 2400,
            'currency' => 'PHP',
        ]);

        $ghost = new UnitRateInterval($real->getAttributes());
        $ghost->setRawAttributes(array_merge($real->getAttributes(), [
            'start_date' => null,
            'end_date' => null,
        ]));

        $unit->setRelation('rateIntervals', collect([$ghost, $real]));

        $result = UnitStayPricing::computeForStay($unit, $start, $end);

        $this->assertNull($result['error']);
        $this->assertSame(4800.0, $result['total']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testComputeForStayUsesPerDayPriceMap(): void
    {
        $unit = $this->makeUnit(['price_per_night' => 1500]);

        UnitRateInterval::create([
            'user_uuid' => $unit->user_uuid,
            'unit_uuid' => $unit->uuid,
            'name' => 'Map',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
            'min_los' => null,
            'max_los' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'days_of_week' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'day_prices' => ['mon' => 1000, 'tue' => 2000, 'wed' => 3000, 'thu' => 4000, 'fri' => 5000, 'sat' => 6000, 'sun' => 7000],
            'base_price' => 2000,
            'currency' => 'PHP',
        ]);

        $result = UnitStayPricing::computeForStay(
            $unit,
            Carbon::today()->addDays(2),
            Carbon::today()->addDays(5)
        );

        $this->assertNull($result['error']);
        $this->assertGreaterThan(0.0, $result['total']);
    }
}
