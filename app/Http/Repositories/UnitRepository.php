<?php

namespace App\Http\Repositories;

use App\Exceptions\NoUnitFoundException;
use App\Helpers\GeneralHelper;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Contracts\Database\Eloquent\Builder;

class UnitRepository
{
    public function __construct(protected Unit $unit)
    {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters): Builder
    {
        return $this->unit->newQuery()
            ->with(['property:uuid,property_name'])
            ->filters($filters)
            ->orderBy('name');
    }

    public function fetchOrThrow(string $key, string $value, ?string $userUuid = null): Unit
    {
        $query = $this->unit->newQuery()->where($key, $value);
        if ($userUuid !== null) {
            $query->where('user_uuid', $userUuid);
        }
        $unit = $query->first();

        if (is_null($unit)) {
            throw new NoUnitFoundException();
        }

        return $unit;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): Unit
    {
        $data = GeneralHelper::unsetUnknownAndNullFields($payload, Unit::DATA);
        if (! array_key_exists('week_schedule', $data) || ! is_array($data['week_schedule'] ?? null)) {
            $data['week_schedule'] = Unit::defaultWeekSchedule();
        }

        return $this->unit->newQuery()->create($data);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Unit $unit, array $payload): bool
    {
        $data = GeneralHelper::unsetUnknownFields($payload, Unit::DATA);

        return $unit->update($data);
    }

    public function delete(Unit $unit): void
    {
        $unit->delete();
    }

    public function resolvePropertyCurrency(string $userUuid, string $propertyUuid): string
    {
        return Property::query()
            ->where('user_uuid', $userUuid)
            ->whereKey($propertyUuid)
            ->value('currency') ?? 'PHP';
    }
}
