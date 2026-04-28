<?php

namespace Tests\Unit\Support;

use App\Models\PromoCode;
use App\Models\User;
use App\Support\DirectPortalPromoCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectPortalPromoCodeTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveReturnsZeroDiscountWhenCodeIsEmpty(): void
    {
        $owner = User::factory()->create();
        $result = DirectPortalPromoCode::resolve($owner->uuid, null, 3, 1500);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['promo']);
        $this->assertSame(0.0, $result['discountAmount']);
        $this->assertSame(1500.0, $result['discountedTotal']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveRejectsUnknownCode(): void
    {
        $owner = User::factory()->create();
        $result = DirectPortalPromoCode::resolve($owner->uuid, 'NOPE', 3, 1500);

        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['message']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveRejectsWhenMinNightsNotMet(): void
    {
        $owner = User::factory()->create();
        PromoCode::factory()->create([
            'user_uuid' => $owner->uuid,
            'code' => 'SAVE10',
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'min_nights' => 5,
            'status' => PromoCode::STATUS_ACTIVE,
        ]);

        $result = DirectPortalPromoCode::resolve($owner->uuid, 'save10', 2, 1500);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('5 night', $result['message']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveRejectsWhenMaxUsesReached(): void
    {
        $owner = User::factory()->create();
        PromoCode::factory()->create([
            'user_uuid' => $owner->uuid,
            'code' => 'CAP1',
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 200,
            'max_uses' => 5,
            'uses_count' => 5,
            'status' => PromoCode::STATUS_ACTIVE,
        ]);

        $result = DirectPortalPromoCode::resolve($owner->uuid, 'CAP1', 1, 1000);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('usage limit', $result['message']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveAppliesPercentageDiscount(): void
    {
        $owner = User::factory()->create();
        PromoCode::factory()->create([
            'user_uuid' => $owner->uuid,
            'code' => 'P25',
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 25,
            'min_nights' => 1,
            'max_uses' => null,
            'status' => PromoCode::STATUS_ACTIVE,
        ]);

        $result = DirectPortalPromoCode::resolve($owner->uuid, 'P25', 3, 1000);

        $this->assertTrue($result['ok']);
        $this->assertSame(250.0, $result['discountAmount']);
        $this->assertSame(750.0, $result['discountedTotal']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveAppliesFixedDiscountCappedAtSubtotal(): void
    {
        $owner = User::factory()->create();
        PromoCode::factory()->create([
            'user_uuid' => $owner->uuid,
            'code' => 'FIX',
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 5000,
            'min_nights' => 1,
            'max_uses' => null,
            'status' => PromoCode::STATUS_ACTIVE,
        ]);

        $result = DirectPortalPromoCode::resolve($owner->uuid, 'FIX', 1, 1000);

        $this->assertTrue($result['ok']);
        $this->assertSame(1000.0, $result['discountAmount']);
        $this->assertSame(0.0, $result['discountedTotal']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testResolveTreatsInactivePromoAsInvalid(): void
    {
        $owner = User::factory()->create();
        PromoCode::factory()->create([
            'user_uuid' => $owner->uuid,
            'code' => 'OFF',
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'min_nights' => 1,
            'status' => PromoCode::STATUS_INACTIVE,
        ]);

        $result = DirectPortalPromoCode::resolve($owner->uuid, 'OFF', 3, 1000);

        $this->assertFalse($result['ok']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDiscountAmountReturnsZeroForZeroSubtotal(): void
    {
        $owner = User::factory()->create();
        $promo = PromoCode::factory()->create([
            'user_uuid' => $owner->uuid,
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 100,
        ]);

        $this->assertSame(0.0, DirectPortalPromoCode::discountAmount($promo, 0));
    }
}
