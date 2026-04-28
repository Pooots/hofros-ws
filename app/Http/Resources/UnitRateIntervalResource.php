<?php

namespace App\Http\Resources;

use App\Constants\GeneralConstants;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitRateIntervalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $days = $this->normalizeDaysOfWeek(is_array($this->days_of_week) ? $this->days_of_week : []);
        $dayPrices = $this->dayPricesFromModel(is_array($this->day_prices) ? $this->day_prices : [], (float) $this->base_price, $days);

        return [
            'uuid' => $this->uuid,
            'unitId' => $this->unit_uuid,
            'name' => $this->name,
            'startDate' => $this->start_date?->format('Y-m-d'),
            'endDate' => $this->end_date?->format('Y-m-d'),
            'minLos' => $this->min_los,
            'maxLos' => $this->max_los,
            'closedToArrival' => (bool) $this->closed_to_arrival,
            'closedToDeparture' => (bool) $this->closed_to_departure,
            'daysOfWeek' => $days,
            'dayPrices' => $dayPrices,
            'basePrice' => (float) $this->base_price,
            'currency' => $this->currency,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, bool>
     */
    private function normalizeDaysOfWeek(array $raw): array
    {
        $out = [];
        foreach (GeneralConstants::RATE_INTERVAL_DAY_KEYS as $key) {
            $out[$key] = ! empty($raw[$key]);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, bool>  $days
     * @return array<string, float>
     */
    private function dayPricesFromModel(array $raw, float $base, array $days): array
    {
        $out = [];
        foreach (GeneralConstants::RATE_INTERVAL_DAY_KEYS as $key) {
            if (array_key_exists($key, $raw) && is_numeric($raw[$key])) {
                $out[$key] = round((float) $raw[$key], 2);
            } else {
                $out[$key] = ! empty($days[$key]) ? round($base, 2) : 0.0;
            }
        }

        return $out;
    }
}
