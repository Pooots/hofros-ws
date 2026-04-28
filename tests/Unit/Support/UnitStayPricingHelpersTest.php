<?php

namespace Tests\Unit\Support;

use App\Support\UnitStayPricing;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class UnitStayPricingHelpersTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testWeekdayKeysMatchExpectedCalendarOrder(): void
    {
        $sunday = Carbon::parse('2025-01-05');
        $monday = Carbon::parse('2025-01-06');
        $tuesday = Carbon::parse('2025-01-07');
        $saturday = Carbon::parse('2025-01-11');

        $this->assertSame('sun', UnitStayPricing::weekdayKey($sunday));
        $this->assertSame('mon', UnitStayPricing::weekdayKey($monday));
        $this->assertSame('tue', UnitStayPricing::weekdayKey($tuesday));
        $this->assertSame('sat', UnitStayPricing::weekdayKey($saturday));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDayKeysConstantHasSevenEntries(): void
    {
        $this->assertCount(7, UnitStayPricing::DAY_KEYS);
        $this->assertSame('sun', UnitStayPricing::DAY_KEYS[0]);
        $this->assertSame('sat', UnitStayPricing::DAY_KEYS[6]);
    }
}
