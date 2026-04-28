<?php

namespace App\Http\Requests\PromoCode;

use App\Models\PromoCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromoCodeRequest extends FormRequest
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
            'uuid' => [
                'required',
                'uuid',
                Rule::exists(PromoCode::class, 'uuid')->whereNull('deleted_at'),
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                Rule::unique('promo_codes', 'code')
                    ->where(fn ($query) => $query->where('user_uuid', $this->user()->uuid))
                    ->ignore($this->input('uuid'), 'uuid'),
            ],
            'discountType' => ['sometimes', 'required', 'string', Rule::in(PromoCode::TYPES)],
            'discountValue' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'minNights' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'maxUses' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'usesCount' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'status' => ['sometimes', 'required', 'string', Rule::in(PromoCode::STATUSES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'uuid' => $this->route('id') ?? $this->route('uuid'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toModelPayload(): array
    {
        $validated = $this->validated();
        $payload = [];

        if (array_key_exists('code', $validated)) {
            $payload['code'] = strtoupper(trim($validated['code']));
        }
        if (array_key_exists('discountType', $validated)) {
            $payload['discount_type'] = $validated['discountType'];
        }
        if (array_key_exists('discountValue', $validated)) {
            $payload['discount_value'] = $validated['discountValue'];
        }
        if (array_key_exists('minNights', $validated)) {
            $payload['min_nights'] = $validated['minNights'];
        }
        if (array_key_exists('maxUses', $validated)) {
            $payload['max_uses'] = $validated['maxUses'];
        }
        if (array_key_exists('usesCount', $validated)) {
            $payload['uses_count'] = $validated['usesCount'];
        }
        if (array_key_exists('status', $validated)) {
            $payload['status'] = $validated['status'];
        }

        return $payload;
    }
}
