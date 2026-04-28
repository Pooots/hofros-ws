<?php

namespace App\Http\Requests\UnitRateInterval;

use App\Constants\GeneralConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class SaveUnitRateIntervalRequest extends FormRequest
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
        $rules = [
            'name' => ['nullable', 'string', 'max:255'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'minLos' => ['nullable', 'integer', 'min:0', 'max:365'],
            'maxLos' => ['nullable', 'integer', 'min:0', 'max:365'],
            'closedToArrival' => ['sometimes', 'boolean'],
            'closedToDeparture' => ['sometimes', 'boolean'],
            'daysOfWeek' => ['required', 'array'],
            'dayPrices' => ['required', 'array'],
            'currency' => ['required', 'string', 'max:16'],
        ];

        foreach (GeneralConstants::RATE_INTERVAL_DAY_KEYS as $key) {
            $rules['dayPrices.' . $key] = ['required', 'numeric', 'min:0'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $minLos = $this->normalizeLos($this->input('minLos'));
            $maxLos = $this->normalizeLos($this->input('maxLos'));

            if ($maxLos !== null && $minLos !== null && $maxLos < $minLos) {
                $v->errors()->add('maxLos', 'Maximum stay must be greater than or equal to minimum stay.');
            }

            $days = (array) $this->input('daysOfWeek', []);
            $hasAny = false;
            foreach (GeneralConstants::RATE_INTERVAL_DAY_KEYS as $key) {
                if (! empty($days[$key])) {
                    $hasAny = true;
                    break;
                }
            }
            if (! $hasAny) {
                $v->errors()->add('daysOfWeek', 'Select at least one day of the week.');
            }
        });
    }

    private function normalizeLos(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param  array<string, bool>  $days
     * @param  array<string, float>  $dayPrices
     * @return array<string, mixed>
     */
    public function toModelPayload(string $userUuid, string $unitUuid, array $days, array $dayPrices, float $basePrice): array
    {
        $validated = $this->validated();

        return [
            'user_uuid' => $userUuid,
            'unit_uuid' => $unitUuid,
            'name' => $validated['name'] ?? null,
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
            'min_los' => $this->normalizeLos($validated['minLos'] ?? null),
            'max_los' => $this->normalizeLos($validated['maxLos'] ?? null),
            'closed_to_arrival' => (bool) ($validated['closedToArrival'] ?? false),
            'closed_to_departure' => (bool) ($validated['closedToDeparture'] ?? false),
            'days_of_week' => $days,
            'day_prices' => $dayPrices,
            'base_price' => $basePrice,
            'currency' => $validated['currency'],
        ];
    }
}
