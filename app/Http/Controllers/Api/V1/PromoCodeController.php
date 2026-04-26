<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromoCodeController extends Controller
{
    private const TYPES = [
        PromoCode::TYPE_PERCENTAGE,
        PromoCode::TYPE_FIXED,
    ];

    private const STATUSES = [
        PromoCode::STATUS_ACTIVE,
        PromoCode::STATUS_INACTIVE,
    ];

    public function index(Request $request): JsonResponse
    {
        $promoCodes = PromoCode::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PromoCode $promoCode) => $this->toPayload($promoCode));

        return response()->json(['promoCodes' => $promoCodes]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('promo_codes', 'code')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'discountType' => ['required', 'string', Rule::in(self::TYPES)],
            'discountValue' => ['required', 'numeric', 'min:0.01'],
            'minNights' => ['required', 'integer', 'min:1', 'max:365'],
            'maxUses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'status' => ['sometimes', 'string', Rule::in(self::STATUSES)],
        ]);

        $promoCode = PromoCode::create([
            'user_id' => $request->user()->id,
            'code' => strtoupper(trim($validated['code'])),
            'discount_type' => $validated['discountType'],
            'discount_value' => $validated['discountValue'],
            'min_nights' => $validated['minNights'],
            'max_uses' => $validated['maxUses'] ?? null,
            'status' => $validated['status'] ?? PromoCode::STATUS_ACTIVE,
        ]);

        return response()->json($this->toPayload($promoCode), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $promoCode = PromoCode::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                Rule::unique('promo_codes', 'code')
                    ->where(fn ($query) => $query->where('user_id', $request->user()->id))
                    ->ignore($promoCode->id),
            ],
            'discountType' => ['sometimes', 'required', 'string', Rule::in(self::TYPES)],
            'discountValue' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'minNights' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'maxUses' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'usesCount' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'status' => ['sometimes', 'required', 'string', Rule::in(self::STATUSES)],
        ]);

        if (array_key_exists('code', $validated)) {
            $promoCode->code = strtoupper(trim($validated['code']));
        }
        if (array_key_exists('discountType', $validated)) {
            $promoCode->discount_type = $validated['discountType'];
        }
        if (array_key_exists('discountValue', $validated)) {
            $promoCode->discount_value = $validated['discountValue'];
        }
        if (array_key_exists('minNights', $validated)) {
            $promoCode->min_nights = $validated['minNights'];
        }
        if (array_key_exists('maxUses', $validated)) {
            $promoCode->max_uses = $validated['maxUses'];
        }
        if (array_key_exists('usesCount', $validated)) {
            $promoCode->uses_count = $validated['usesCount'];
        }
        if (array_key_exists('status', $validated)) {
            $promoCode->status = $validated['status'];
        }

        $promoCode->save();

        return response()->json($this->toPayload($promoCode->fresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $promoCode = PromoCode::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $promoCode->delete();

        return response()->json(['message' => 'Promo code deleted.']);
    }

    private function toPayload(PromoCode $promoCode): array
    {
        return [
            'id' => $promoCode->id,
            'code' => $promoCode->code,
            'discountType' => $promoCode->discount_type,
            'discountValue' => (float) $promoCode->discount_value,
            'minNights' => $promoCode->min_nights,
            'maxUses' => $promoCode->max_uses,
            'usesCount' => $promoCode->uses_count,
            'status' => $promoCode->status,
            'createdAt' => $promoCode->created_at?->toIso8601String(),
        ];
    }
}
