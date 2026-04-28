<?php

namespace App\Http\Repositories;

use App\Constants\GeneralConstants;
use App\Exceptions\NoUnitRateIntervalFoundException;
use App\Helpers\GeneralHelper;
use App\Models\UnitRateInterval;
use Illuminate\Contracts\Database\Eloquent\Builder;

class UnitRateIntervalRepository
{
    public function __construct(protected UnitRateInterval $interval)
    {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters): Builder
    {
        return $this->interval->newQuery()
            ->filters($filters)
            ->orderBy('start_date')
            ->orderBy('created_at', 'desc');
    }

    public function fetchOrThrow(string $key, string $value, ?string $userUuid = null, ?string $unitUuid = null): UnitRateInterval
    {
        $query = $this->interval->newQuery()->where($key, $value);
        if ($userUuid !== null) {
            $query->where('user_uuid', $userUuid);
        }
        if ($unitUuid !== null) {
            $query->where('unit_uuid', $unitUuid);
        }
        $row = $query->first();

        if (is_null($row)) {
            throw new NoUnitRateIntervalFoundException();
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): UnitRateInterval
    {
        $data = GeneralHelper::unsetUnknownAndNullFields($payload, UnitRateInterval::DATA);

        return $this->interval->newQuery()->create($data);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(UnitRateInterval $row, array $payload): bool
    {
        $data = GeneralHelper::unsetUnknownFields($payload, UnitRateInterval::DATA);

        return $row->update($data);
    }

    public function delete(UnitRateInterval $row): void
    {
        $row->delete();
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array<string, bool>
     */
    public function normalizeDaysOfWeek(?array $raw): array
    {
        $out = [];
        foreach (GeneralConstants::RATE_INTERVAL_DAY_KEYS as $key) {
            $out[$key] = ! empty($raw[$key]);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array<string, float>
     */
    public function normalizeDayPrices(?array $raw): array
    {
        $out = [];
        foreach (GeneralConstants::RATE_INTERVAL_DAY_KEYS as $key) {
            $v = $raw[$key] ?? 0;
            $out[$key] = round((float) $v, 2);
        }

        return $out;
    }

    /**
     * @param  array<string, float>  $dayPrices
     */
    public function deriveBasePrice(array $dayPrices): float
    {
        if ($dayPrices === []) {
            return 0.0;
        }

        return round(max($dayPrices), 2);
    }
}
