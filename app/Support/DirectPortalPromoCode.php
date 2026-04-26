<?php

namespace App\Support;

use App\Models\PromoCode;

final class DirectPortalPromoCode
{
    /**
     * @return array{ok:bool, message:?string, promo:?PromoCode, discountAmount:float, discountedTotal:float}
     */
    public static function resolve(int $userId, ?string $rawCode, int $nights, float $subtotal): array
    {
        $code = strtoupper(trim((string) $rawCode));
        $base = round(max(0.0, $subtotal), 2);
        if ($code === '') {
            return [
                'ok' => true,
                'message' => null,
                'promo' => null,
                'discountAmount' => 0.0,
                'discountedTotal' => $base,
            ];
        }

        $promo = PromoCode::query()
            ->where('user_id', $userId)
            ->where('code', $code)
            ->where('status', PromoCode::STATUS_ACTIVE)
            ->first();

        if ($promo === null) {
            return [
                'ok' => false,
                'message' => 'Promo code is invalid or inactive.',
                'promo' => null,
                'discountAmount' => 0.0,
                'discountedTotal' => $base,
            ];
        }

        if ($nights < (int) $promo->min_nights) {
            return [
                'ok' => false,
                'message' => sprintf(
                    'Promo code %s requires at least %d night(s).',
                    $promo->code,
                    (int) $promo->min_nights
                ),
                'promo' => null,
                'discountAmount' => 0.0,
                'discountedTotal' => $base,
            ];
        }

        $maxUses = $promo->max_uses;
        if ($maxUses !== null && (int) $promo->uses_count >= (int) $maxUses) {
            return [
                'ok' => false,
                'message' => 'Promo code has reached its usage limit.',
                'promo' => null,
                'discountAmount' => 0.0,
                'discountedTotal' => $base,
            ];
        }

        $discount = self::discountAmount($promo, $base);
        $nextTotal = round(max(0.0, $base - $discount), 2);

        return [
            'ok' => true,
            'message' => null,
            'promo' => $promo,
            'discountAmount' => $discount,
            'discountedTotal' => $nextTotal,
        ];
    }

    public static function discountAmount(PromoCode $promo, float $subtotal): float
    {
        $base = round(max(0.0, $subtotal), 2);
        $raw = $promo->discount_type === PromoCode::TYPE_PERCENTAGE
            ? $base * (((float) $promo->discount_value) / 100)
            : (float) $promo->discount_value;

        return round(min($base, max(0.0, $raw)), 2);
    }
}

