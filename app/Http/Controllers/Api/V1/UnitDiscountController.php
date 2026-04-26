<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\UnitDiscount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitDiscountController extends Controller
{
    private const TYPES = [
        UnitDiscount::TYPE_EARLY_BIRD,
        UnitDiscount::TYPE_LONG_STAY,
        UnitDiscount::TYPE_LAST_MINUTE,
        UnitDiscount::TYPE_WEEKEND_DISCOUNT,
        UnitDiscount::TYPE_DATE_RANGE,
    ];

    private const STATUSES = [
        UnitDiscount::STATUS_ACTIVE,
        UnitDiscount::STATUS_INACTIVE,
    ];

    public function index(Request $request): JsonResponse
    {
        $discounts = UnitDiscount::query()
            ->with(['unit:id,name'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (UnitDiscount $discount) => $this->toPayload($discount));

        return response()->json(['unitDiscounts' => $discounts]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unitId' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'discountType' => ['required', 'string', Rule::in(self::TYPES)],
            'discountPercent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'minDaysInAdvance' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'minNights' => ['nullable', 'integer', 'min:1', 'max:365'],
            'validFrom' => ['nullable', 'date'],
            'validTo' => ['nullable', 'date', 'after_or_equal:validFrom'],
            'status' => ['sometimes', 'string', Rule::in(self::STATUSES)],
        ]);

        $discount = UnitDiscount::create([
            'user_id' => $request->user()->id,
            'unit_id' => $validated['unitId'],
            'discount_type' => $validated['discountType'],
            'discount_percent' => $validated['discountPercent'],
            'min_days_in_advance' => $validated['minDaysInAdvance'] ?? null,
            'min_nights' => $validated['minNights'] ?? null,
            'valid_from' => $validated['validFrom'] ?? null,
            'valid_to' => $validated['validTo'] ?? null,
            'status' => $validated['status'] ?? UnitDiscount::STATUS_ACTIVE,
        ]);

        return response()->json($this->toPayload($discount->load('unit:id,name')), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $discount = UnitDiscount::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $validated = $request->validate([
            'unitId' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'discountType' => ['sometimes', 'required', 'string', Rule::in(self::TYPES)],
            'discountPercent' => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:100'],
            'minDaysInAdvance' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3650'],
            'minNights' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'validFrom' => ['sometimes', 'nullable', 'date'],
            'validTo' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'required', 'string', Rule::in(self::STATUSES)],
        ]);

        $validFrom = array_key_exists('validFrom', $validated) ? $validated['validFrom'] : $discount->valid_from?->format('Y-m-d');
        $validTo = array_key_exists('validTo', $validated) ? $validated['validTo'] : $discount->valid_to?->format('Y-m-d');
        if ($validFrom !== null && $validTo !== null && $validTo < $validFrom) {
            return response()->json(['message' => 'The validTo must be a date after or equal to validFrom.'], 422);
        }

        if (array_key_exists('unitId', $validated)) {
            $discount->unit_id = $validated['unitId'];
        }
        if (array_key_exists('discountType', $validated)) {
            $discount->discount_type = $validated['discountType'];
        }
        if (array_key_exists('discountPercent', $validated)) {
            $discount->discount_percent = $validated['discountPercent'];
        }
        if (array_key_exists('minDaysInAdvance', $validated)) {
            $discount->min_days_in_advance = $validated['minDaysInAdvance'];
        }
        if (array_key_exists('minNights', $validated)) {
            $discount->min_nights = $validated['minNights'];
        }
        if (array_key_exists('validFrom', $validated)) {
            $discount->valid_from = $validated['validFrom'];
        }
        if (array_key_exists('validTo', $validated)) {
            $discount->valid_to = $validated['validTo'];
        }
        if (array_key_exists('status', $validated)) {
            $discount->status = $validated['status'];
        }

        $discount->save();

        return response()->json($this->toPayload($discount->fresh()->load('unit:id,name')));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $discount = UnitDiscount::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $discount->delete();

        return response()->json(['message' => 'Unit discount deleted.']);
    }

    private function toPayload(UnitDiscount $discount): array
    {
        return [
            'id' => $discount->id,
            'unitId' => $discount->unit_id,
            'unitName' => $discount->unit?->name ?? Unit::query()->whereKey($discount->unit_id)->value('name'),
            'discountType' => $discount->discount_type,
            'discountPercent' => (float) $discount->discount_percent,
            'minDaysInAdvance' => $discount->min_days_in_advance,
            'minNights' => $discount->min_nights,
            'validFrom' => $discount->valid_from?->format('Y-m-d'),
            'validTo' => $discount->valid_to?->format('Y-m-d'),
            'status' => $discount->status,
            'createdAt' => $discount->created_at?->toIso8601String(),
        ];
    }
}
