<?php

namespace App\Http\Controllers\Api\V1\Configuration;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\UnitRateInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UnitRateIntervalController extends Controller
{
    /** API + DB keys for days of week (prices + availability). */
    private const DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function index(Request $request, int $unitId): JsonResponse
    {
        $unit = $this->resolveUnit($request, $unitId);

        $intervals = UnitRateInterval::query()
            ->where('user_id', $request->user()->id)
            ->where('unit_id', $unit->id)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (UnitRateInterval $row) => $this->toPayload($row));

        return response()->json(['intervals' => $intervals]);
    }

    public function store(Request $request, int $unitId): JsonResponse
    {
        $unit = $this->resolveUnit($request, $unitId);
        $validated = $this->validatedPayload($request);
        $days = $this->normalizeDaysOfWeek($validated['daysOfWeek'] ?? []);
        $this->assertAtLeastOneDay($days);
        $dayPrices = $this->normalizeDayPrices($validated['dayPrices'] ?? []);
        $basePrice = $this->deriveBasePrice($dayPrices);

        $row = UnitRateInterval::create([
            'user_id' => $request->user()->id,
            'unit_id' => $unit->id,
            'name' => $validated['name'] ?? null,
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
            'min_los' => $validated['minLos'],
            'max_los' => $validated['maxLos'] ?? null,
            'closed_to_arrival' => (bool) ($validated['closedToArrival'] ?? false),
            'closed_to_departure' => (bool) ($validated['closedToDeparture'] ?? false),
            'days_of_week' => $days,
            'day_prices' => $dayPrices,
            'base_price' => $basePrice,
            'currency' => $validated['currency'],
        ]);

        return response()->json($this->toPayload($row), 201);
    }

    public function update(Request $request, int $unitId, int $intervalId): JsonResponse
    {
        $this->resolveUnit($request, $unitId);
        $row = UnitRateInterval::query()
            ->where('user_id', $request->user()->id)
            ->where('unit_id', $unitId)
            ->whereKey($intervalId)
            ->firstOrFail();

        $validated = $this->validatedPayload($request);
        $days = $this->normalizeDaysOfWeek($validated['daysOfWeek'] ?? []);
        $this->assertAtLeastOneDay($days);
        $dayPrices = $this->normalizeDayPrices($validated['dayPrices'] ?? []);
        $basePrice = $this->deriveBasePrice($dayPrices);

        $row->fill([
            'name' => $validated['name'] ?? null,
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
            'min_los' => $validated['minLos'],
            'max_los' => $validated['maxLos'] ?? null,
            'closed_to_arrival' => (bool) ($validated['closedToArrival'] ?? false),
            'closed_to_departure' => (bool) ($validated['closedToDeparture'] ?? false),
            'days_of_week' => $days,
            'day_prices' => $dayPrices,
            'base_price' => $basePrice,
            'currency' => $validated['currency'],
        ]);
        $row->save();

        return response()->json($this->toPayload($row->fresh()));
    }

    public function destroy(Request $request, int $unitId, int $intervalId): JsonResponse
    {
        $this->resolveUnit($request, $unitId);
        $row = UnitRateInterval::query()
            ->where('user_id', $request->user()->id)
            ->where('unit_id', $unitId)
            ->whereKey($intervalId)
            ->firstOrFail();

        $row->delete();

        return response()->json(['message' => 'Interval deleted.']);
    }

    private function resolveUnit(Request $request, int $unitId): Unit
    {
        return Unit::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($unitId)
            ->firstOrFail();
    }

    private function validatedPayload(Request $request): array
    {
        $dayPriceRules = [];
        foreach (self::DAY_KEYS as $key) {
            $dayPriceRules['dayPrices.'.$key] = ['required', 'numeric', 'min:0'];
        }

        $rules = array_merge([
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
        ], $dayPriceRules);

        $validated = $request->validate($rules);

        $minLos = $validated['minLos'] ?? null;
        if ($minLos === 0) {
            $minLos = null;
        }
        $validated['minLos'] = $minLos;

        $maxLos = $validated['maxLos'] ?? null;
        if ($maxLos === 0) {
            $maxLos = null;
        }
        $validated['maxLos'] = $maxLos;

        if ($maxLos !== null && $minLos !== null && $maxLos < $minLos) {
            throw ValidationException::withMessages([
                'maxLos' => ['Maximum stay must be greater than or equal to minimum stay.'],
            ]);
        }

        return $validated;
    }

    /** @param  array<string, mixed>|null  $raw */
    private function normalizeDaysOfWeek(?array $raw): array
    {
        $out = [];
        foreach (self::DAY_KEYS as $key) {
            $out[$key] = ! empty($raw[$key]);
        }

        return $out;
    }

    /** @param  array<string, mixed>|null  $raw */
    private function normalizeDayPrices(?array $raw): array
    {
        $out = [];
        foreach (self::DAY_KEYS as $key) {
            $v = $raw[$key] ?? 0;
            $out[$key] = round((float) $v, 2);
        }

        return $out;
    }

    /** @param  array<string, float>  $dayPrices */
    private function deriveBasePrice(array $dayPrices): float
    {
        if ($dayPrices === []) {
            return 0.0;
        }

        return round(max($dayPrices), 2);
    }

    /** @param  array<string, bool>  $days */
    private function assertAtLeastOneDay(array $days): void
    {
        foreach ($days as $on) {
            if ($on) {
                return;
            }
        }
        throw ValidationException::withMessages([
            'daysOfWeek' => ['Select at least one day of the week.'],
        ]);
    }

    private function toPayload(UnitRateInterval $row): array
    {
        $days = $this->normalizeDaysOfWeek(is_array($row->days_of_week) ? $row->days_of_week : []);
        $dayPrices = $this->dayPricesFromModel($row, $days);

        return [
            'id' => $row->id,
            'unitId' => $row->unit_id,
            'name' => $row->name,
            'startDate' => $row->start_date?->format('Y-m-d'),
            'endDate' => $row->end_date?->format('Y-m-d'),
            'minLos' => $row->min_los,
            'maxLos' => $row->max_los,
            'closedToArrival' => (bool) $row->closed_to_arrival,
            'closedToDeparture' => (bool) $row->closed_to_departure,
            'daysOfWeek' => $days,
            'dayPrices' => $dayPrices,
            'basePrice' => (float) $row->base_price,
            'currency' => $row->currency,
        ];
    }

    /** @param  array<string, bool>  $days */
    private function dayPricesFromModel(UnitRateInterval $row, array $days): array
    {
        $raw = is_array($row->day_prices) ? $row->day_prices : [];
        $base = (float) $row->base_price;
        $out = [];
        foreach (self::DAY_KEYS as $key) {
            if (array_key_exists($key, $raw) && is_numeric($raw[$key])) {
                $out[$key] = round((float) $raw[$key], 2);
            } else {
                $out[$key] = ! empty($days[$key]) ? round($base, 2) : 0.0;
            }
        }

        return $out;
    }
}
