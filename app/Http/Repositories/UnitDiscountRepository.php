<?php

namespace App\Http\Repositories;

use App\Exceptions\NoUnitDiscountFoundException;
use App\Helpers\GeneralHelper;
use App\Models\UnitDiscount;
use Illuminate\Contracts\Database\Eloquent\Builder;

class UnitDiscountRepository
{
    public function __construct(protected UnitDiscount $discount)
    {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters): Builder
    {
        return $this->discount->newQuery()
            ->with(['unit:uuid,name'])
            ->filters($filters)
            ->orderByDesc('created_at');
    }

    public function fetchOrThrow(string $key, string $value, ?string $userUuid = null): UnitDiscount
    {
        $query = $this->discount->newQuery()->where($key, $value);
        if ($userUuid !== null) {
            $query->where('user_uuid', $userUuid);
        }
        $discount = $query->first();

        if (is_null($discount)) {
            throw new NoUnitDiscountFoundException();
        }

        return $discount;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): UnitDiscount
    {
        $data = GeneralHelper::unsetUnknownAndNullFields($payload, UnitDiscount::DATA);

        return $this->discount->newQuery()->create($data);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(UnitDiscount $discount, array $payload): bool
    {
        $data = GeneralHelper::unsetUnknownFields($payload, UnitDiscount::DATA);

        return $discount->update($data);
    }

    public function delete(UnitDiscount $discount): void
    {
        $discount->delete();
    }
}
