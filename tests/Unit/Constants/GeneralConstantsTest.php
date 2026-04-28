<?php

namespace Tests\Unit\Constants;

use App\Constants\GeneralConstants;
use PHPUnit\Framework\TestCase;

class GeneralConstantsTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testStatusConstantsPresent(): void
    {
        $this->assertSame('active', GeneralConstants::GENERAL_STATUSES['ACTIVE']);
        $this->assertSame('inactive', GeneralConstants::GENERAL_STATUSES['INACTIVE']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookingStatusConstants(): void
    {
        $this->assertSame('pending', GeneralConstants::BOOKING_STATUSES['PENDING']);
        $this->assertCount(6, GeneralConstants::BOOKING_STATUSES);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBookingSourcesConstants(): void
    {
        $this->assertSame('direct_portal', GeneralConstants::BOOKING_SOURCES['DIRECT_PORTAL']);
        $this->assertSame('manual', GeneralConstants::BOOKING_SOURCES['MANUAL']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPaymentTypesIncludesGcash(): void
    {
        $this->assertContains('gcash', GeneralConstants::PAYMENT_TYPES);
        $this->assertContains('bank_transfer', GeneralConstants::PAYMENT_TYPES);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPaymentKindsConstant(): void
    {
        $this->assertSame(['PAYMENT' => 'payment', 'REFUND' => 'refund'], GeneralConstants::PAYMENT_KINDS);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPromoCodeTypes(): void
    {
        $this->assertSame('percentage', GeneralConstants::PROMO_CODE_TYPES['PERCENTAGE']);
        $this->assertSame('fixed', GeneralConstants::PROMO_CODE_TYPES['FIXED']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnitDiscountTypesPresent(): void
    {
        $this->assertContains('early_bird', GeneralConstants::UNIT_DISCOUNT_TYPES);
        $this->assertContains('long_stay', GeneralConstants::UNIT_DISCOUNT_TYPES);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testTeamMemberRolesPresent(): void
    {
        $this->assertContains('admin', GeneralConstants::TEAM_MEMBER_ROLES);
        $this->assertContains('manager', GeneralConstants::TEAM_MEMBER_ROLES);
        $this->assertContains('staff', GeneralConstants::TEAM_MEMBER_ROLES);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testRateIntervalDayKeysStartsWithSunday(): void
    {
        $this->assertSame('sun', GeneralConstants::RATE_INTERVAL_DAY_KEYS[0]);
        $this->assertCount(7, GeneralConstants::RATE_INTERVAL_DAY_KEYS);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testWeekScheduleKeysStartsWithMonday(): void
    {
        $this->assertSame('mon', GeneralConstants::WEEK_SCHEDULE_KEYS[0]);
        $this->assertSame('sun', GeneralConstants::WEEK_SCHEDULE_KEYS[6]);
    }
}
