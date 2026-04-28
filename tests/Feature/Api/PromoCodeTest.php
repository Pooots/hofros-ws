<?php

namespace Tests\Feature\Api;

use App\Models\PromoCode;
use App\Models\User;

class PromoCodeTest extends ApiTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexReturnsOnlyOwnedPromoCodes(): void
    {
        $owner = $this->authenticate();
        PromoCode::factory()->count(2)->create(['user_uuid' => $owner->uuid]);
        PromoCode::factory()->create(['user_uuid' => User::factory()->create()->uuid]);

        $this->getJson('/api/v1/discounts/promo-codes')
            ->assertOk()
            ->assertJsonCount(2, 'promoCodes');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreCreatesPromoCode(): void
    {
        $this->authenticate();

        $this->postJson('/api/v1/discounts/promo-codes', [
            'code' => 'SUMMER25',
            'discountType' => PromoCode::TYPE_PERCENTAGE,
            'discountValue' => 25,
            'minNights' => 2,
            'maxUses' => 100,
            'status' => PromoCode::STATUS_ACTIVE,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'SUMMER25')
            ->assertJsonPath('data.discountValue', 25);

        $this->assertDatabaseHas('promo_codes', [
            'code' => 'SUMMER25',
            'user_uuid' => $this->authUser->uuid,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreRejectsDuplicateCodeForSameUser(): void
    {
        $owner = $this->authenticate();
        PromoCode::factory()->create([
            'user_uuid' => $owner->uuid,
            'code' => 'TAKEN',
        ]);

        $this->postJson('/api/v1/discounts/promo-codes', [
            'code' => 'TAKEN',
            'discountType' => PromoCode::TYPE_PERCENTAGE,
            'discountValue' => 10,
            'minNights' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testStoreValidatesRequiredFields(): void
    {
        $this->authenticate();

        $this->postJson('/api/v1/discounts/promo-codes', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'discountType', 'discountValue', 'minNights']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateModifiesPromoCode(): void
    {
        $owner = $this->authenticate();
        $promo = PromoCode::factory()->create(['user_uuid' => $owner->uuid]);

        $this->putJson('/api/v1/discounts/promo-codes/'.$promo->uuid, [
            'discountValue' => 35,
            'status' => PromoCode::STATUS_INACTIVE,
        ])
            ->assertOk()
            ->assertJsonPath('data.discountValue', 35)
            ->assertJsonPath('data.status', PromoCode::STATUS_INACTIVE);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDestroyRemovesPromoCode(): void
    {
        $owner = $this->authenticate();
        $promo = PromoCode::factory()->create(['user_uuid' => $owner->uuid]);

        $this->deleteJson('/api/v1/discounts/promo-codes/'.$promo->uuid)
            ->assertOk();

        $this->assertSoftDeleted('promo_codes', ['uuid' => $promo->uuid]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdatePersistsAllOptionalFields(): void
    {
        $owner = $this->authenticate();
        $promo = PromoCode::factory()->create([
            'user_uuid' => $owner->uuid,
            'code' => 'OLD',
            'min_nights' => 1,
            'max_uses' => null,
            'uses_count' => 0,
        ]);

        $this->putJson('/api/v1/discounts/promo-codes/'.$promo->uuid, [
            'code' => 'NEW',
            'discountType' => PromoCode::TYPE_PERCENTAGE,
            'discountValue' => 12,
            'minNights' => 2,
            'maxUses' => 50,
            'usesCount' => 5,
            'status' => PromoCode::STATUS_ACTIVE,
        ])->assertOk()
            ->assertJsonPath('data.code', 'NEW')
            ->assertJsonPath('data.discountType', PromoCode::TYPE_PERCENTAGE)
            ->assertJsonPath('data.minNights', 2)
            ->assertJsonPath('data.maxUses', 50)
            ->assertJsonPath('data.usesCount', 5);
    }
}
