<?php

namespace Tests\Unit\Support;

use App\Support\MerchantBookingAnalytics;
use PHPUnit\Framework\TestCase;

class MerchantBookingAnalyticsHelpersTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testPctChangeReturnsZeroWhenBothZero(): void
    {
        $this->assertSame(0.0, MerchantBookingAnalytics::pctChange(0.0, 0.0));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPctChangeReturns100WhenPreviousIsZeroAndCurrentPositive(): void
    {
        $this->assertSame(100.0, MerchantBookingAnalytics::pctChange(50.0, 0.0));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPctChangeComputesPercentage(): void
    {
        $this->assertSame(50.0, MerchantBookingAnalytics::pctChange(150.0, 100.0));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testPctChangeReturnsZeroWhenCurrentZeroPreviousPositive(): void
    {
        $this->assertSame(-100.0, MerchantBookingAnalytics::pctChange(0.0, 100.0));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testMapSourceKeyLabelKnownKeys(): void
    {
        $this->assertSame(['key' => 'direct', 'label' => 'Direct'], MerchantBookingAnalytics::mapSourceKeyLabel('direct_portal'));
        $this->assertSame(['key' => 'manual', 'label' => 'Manual'], MerchantBookingAnalytics::mapSourceKeyLabel('manual'));
        $this->assertSame(['key' => 'airbnb', 'label' => 'Airbnb'], MerchantBookingAnalytics::mapSourceKeyLabel('airbnb'));
        $this->assertSame(['key' => 'booking_com', 'label' => 'Booking.com'], MerchantBookingAnalytics::mapSourceKeyLabel('booking.com'));
        $this->assertSame(['key' => 'booking_com', 'label' => 'Booking.com'], MerchantBookingAnalytics::mapSourceKeyLabel('booking_com'));
        $this->assertSame(['key' => 'expedia', 'label' => 'Expedia'], MerchantBookingAnalytics::mapSourceKeyLabel('expedia'));
        $this->assertSame(['key' => 'vrbo', 'label' => 'VRBO'], MerchantBookingAnalytics::mapSourceKeyLabel('vrbo'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testMapSourceKeyLabelUnknownFallsBack(): void
    {
        $this->assertSame(['key' => 'other', 'label' => 'Other'], MerchantBookingAnalytics::mapSourceKeyLabel(''));
        $this->assertSame(['key' => 'walk_in', 'label' => 'Walk In'], MerchantBookingAnalytics::mapSourceKeyLabel('walk_in'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testCurrencyConstantsArePhp(): void
    {
        $this->assertSame('PHP', MerchantBookingAnalytics::CURRENCY_CODE);
        $this->assertNotEmpty(MerchantBookingAnalytics::CURRENCY_SYMBOL);
    }
}
