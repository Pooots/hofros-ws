<?php

namespace App\Http\Repositories;

use App\Exceptions\NoPropertyFoundException;
use App\Helpers\GeneralHelper;
use App\Models\Property;
use Illuminate\Contracts\Database\Eloquent\Builder;

class PropertyRepository
{
    public function __construct(protected Property $property)
    {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters): Builder
    {
        return $this->property->newQuery()
            ->filters($filters)
            ->orderByDesc('updated_at');
    }

    public function fetchOrThrow(string $key, string $value, ?string $userUuid = null): Property
    {
        $query = $this->property->newQuery()->where($key, $value);
        if ($userUuid !== null) {
            $query->where('user_uuid', $userUuid);
        }
        $property = $query->first();

        if (is_null($property)) {
            throw new NoPropertyFoundException();
        }

        return $property;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): Property
    {
        $data = GeneralHelper::unsetUnknownAndNullFields($payload, Property::DATA);

        return $this->property->newQuery()->create($data);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Property $property, array $payload): bool
    {
        $data = GeneralHelper::unsetUnknownFields($payload, Property::DATA);

        return $property->update($data);
    }

    public function delete(Property $property): void
    {
        $property->delete();
    }
}
