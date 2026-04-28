<?php

namespace App\Http\Requests\PromoCode;

use App\Models\PromoCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('promo_codes', 'code')->where(fn ($query) => $query->where('user_uuid', $this->user()->uuid)),
            ],
            'discountType' => ['required', 'string', Rule::in(PromoCode::TYPES)],
            'discountValue' => ['required', 'numeric', 'min:0.01'],
            'minNights' => ['required', 'integer', 'min:1', 'max:365'],
            'maxUses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'status' => ['sometimes', 'string', Rule::in(PromoCode::STATUSES)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toModelPayload(): array
    {
        $validated = $this->validated();

        return [
            'user_uuid' => $this->user()->uuid,
            'code' => strtoupper(trim($validated['code'])),
            'discount_type' => $validated['discountType'],
            'discount_value' => $validated['discountValue'],
            'min_nights' => $validated['minNights'],
            'max_uses' => $validated['maxUses'] ?? null,
            'status' => $validated['status'] ?? PromoCode::STATUS_ACTIVE,
        ];
    }
}
