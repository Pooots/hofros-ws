<?php

namespace Tests\Unit\Repositories;

use App\Http\Repositories\BookingRepository;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingRepositoryEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private function repo(): BookingRepository
    {
        return new BookingRepository(new Booking());
    }

    private function ownerWithUnit(array $unitOverrides = []): array
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['user_uuid' => $owner->uuid]);
        $unit = Unit::factory()->create(array_merge([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
            'status' => Unit::STATUS_ACTIVE,
        ], $unitOverrides));

        return [$owner, $property, $unit];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUniqueReferenceProducesPrefixedString(): void
    {
        $reference = $this->repo()->uniqueReference();
        $this->assertStringStartsWith('HFR-', $reference);
        $this->assertGreaterThan(4, strlen($reference));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGetAvailableMatchingUnitsExcludesUnitsWithDateBlocks(): void
    {
        [$owner, $property, $listed] = $this->ownerWithUnit();
        [, , $sibling] = $this->ownerWithUnit([
            'user_uuid' => $owner->uuid,
            'property_uuid' => $property->uuid,
            'type' => 'Studio',
            'max_guests' => 2,
            'bedrooms' => 1,
            'beds' => 1,
        ]);

        UnitDateBlock::create([
            'user_uuid' => $owner->uuid,
            'unit_uuid' => $sibling->uuid,
            'start_date' => Carbon::today()->addDays(5)->toDateString(),
            'end_date' => Carbon::today()->addDays(10)->toDateString(),
            'label' => 'Maintenance',
        ]);

        $available = $this->repo()->getAvailableMatchingUnits(
            $owner->uuid,
            $listed,
            Carbon::today()->addDays(6),
            Carbon::today()->addDays(8),
            null,
        );

        $uuids = $available->pluck('uuid')->all();
        $this->assertContains($listed->uuid, $uuids);
        $this->assertNotContains($sibling->uuid, $uuids);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGetAvailableMatchingUnitsHandlesTemplateWithNullType(): void
    {
        [$owner] = $this->ownerWithUnit();

        $template = new Unit();
        $template->user_uuid = $owner->uuid;
        $template->property_uuid = (string) \Illuminate\Support\Str::uuid();
        $template->type = null;
        $template->max_guests = 2;
        $template->bedrooms = 1;
        $template->beds = 1;

        $available = $this->repo()->getAvailableMatchingUnits(
            $owner->uuid,
            $template,
            Carbon::today()->addDays(60),
            Carbon::today()->addDays(62),
            null,
        );

        $this->assertSame(0, $available->count());
    }
}
