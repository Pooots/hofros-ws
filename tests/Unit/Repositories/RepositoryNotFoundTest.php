<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\NoPromoCodeFoundException;
use App\Exceptions\NoTeamMemberFoundException;
use App\Exceptions\NoUnitDateBlockFoundException;
use App\Exceptions\NoUnitDiscountFoundException;
use App\Exceptions\NoUnitFoundException;
use App\Exceptions\NoUnitRateIntervalFoundException;
use App\Http\Repositories\PromoCodeRepository;
use App\Http\Repositories\TeamMemberRepository;
use App\Http\Repositories\UnitDateBlockRepository;
use App\Http\Repositories\UnitDiscountRepository;
use App\Http\Repositories\UnitRateIntervalRepository;
use App\Http\Repositories\UnitRepository;
use App\Models\PromoCode;
use App\Models\Property;
use App\Models\TeamMember;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use App\Models\UnitDiscount;
use App\Models\UnitRateInterval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RepositoryNotFoundTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPromoCodeRepositoryThrowsWhenMissing(): void
    {
        $this->expectException(NoPromoCodeFoundException::class);
        (new PromoCodeRepository(new PromoCode()))
            ->fetchOrThrow('uuid', (string) Str::uuid(), (string) Str::uuid());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testTeamMemberRepositoryThrowsWhenMissing(): void
    {
        $this->expectException(NoTeamMemberFoundException::class);
        (new TeamMemberRepository(new TeamMember()))
            ->fetchOrThrow('uuid', (string) Str::uuid(), (string) Str::uuid());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitDateBlockRepositoryThrowsWhenMissing(): void
    {
        $this->expectException(NoUnitDateBlockFoundException::class);
        (new UnitDateBlockRepository(new UnitDateBlock()))
            ->fetchOrThrow('uuid', (string) Str::uuid(), (string) Str::uuid());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitDiscountRepositoryThrowsWhenMissing(): void
    {
        $this->expectException(NoUnitDiscountFoundException::class);
        (new UnitDiscountRepository(new UnitDiscount()))
            ->fetchOrThrow('uuid', (string) Str::uuid(), (string) Str::uuid());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitRepositoryThrowsWhenMissing(): void
    {
        $this->expectException(NoUnitFoundException::class);
        (new UnitRepository(new Unit()))
            ->fetchOrThrow('uuid', (string) Str::uuid(), (string) Str::uuid());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitRateIntervalRepositoryThrowsWhenMissing(): void
    {
        $this->expectException(NoUnitRateIntervalFoundException::class);
        (new UnitRateIntervalRepository(new UnitRateInterval()))
            ->fetchOrThrow('uuid', (string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitRateIntervalRepositoryDeriveBasePriceWithEmptyArray(): void
    {
        $repo = new UnitRateIntervalRepository(new UnitRateInterval());
        $this->assertSame(0.0, $repo->deriveBasePrice([]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitRateIntervalRepositoryDeriveBasePriceUsesMaxValue(): void
    {
        $repo = new UnitRateIntervalRepository(new UnitRateInterval());
        $this->assertSame(2200.50, $repo->deriveBasePrice(['mon' => 1500.00, 'tue' => 2200.50, 'wed' => 1800.00]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitRateIntervalRepositoryNormalizesDaysOfWeek(): void
    {
        $repo = new UnitRateIntervalRepository(new UnitRateInterval());
        $out = $repo->normalizeDaysOfWeek(['mon' => true, 'fri' => 1]);
        $this->assertTrue($out['mon']);
        $this->assertTrue($out['fri']);
        $this->assertFalse($out['sat']);
        $this->assertCount(7, $out);

        $defaults = $repo->normalizeDaysOfWeek(null);
        $this->assertCount(7, $defaults);
        foreach ($defaults as $value) {
            $this->assertFalse($value);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitRateIntervalRepositoryNormalizesDayPrices(): void
    {
        $repo = new UnitRateIntervalRepository(new UnitRateInterval());
        $out = $repo->normalizeDayPrices(['mon' => '1000.50', 'tue' => 'not-a-number']);
        $this->assertSame(1000.50, $out['mon']);
        $this->assertSame(0.0, $out['tue']);
        $this->assertCount(7, $out);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitRepositoryCreateAppliesDefaultWeekScheduleWhenMissing(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);

        $repo = new UnitRepository(new Unit());
        $unit = $repo->create([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'name' => 'Sample Unit',
            'type' => 'room',
            'status' => 'active',
            'currency' => 'PHP',
            'price_per_night' => 1500.00,
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'bathrooms' => 1.0,
        ]);

        $this->assertSame(Unit::defaultWeekSchedule(), $unit->week_schedule);
    }
}
