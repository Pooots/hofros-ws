<?php

namespace App\Http\Repositories;

use App\Exceptions\NoUnitDateBlockFoundException;
use App\Helpers\GeneralHelper;
use App\Models\UnitDateBlock;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class UnitDateBlockRepository
{
    public function __construct(protected UnitDateBlock $block)
    {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters): Builder
    {
        return $this->block->newQuery()
            ->with(['unit:uuid,name,type'])
            ->filters($filters)
            ->orderByDesc('start_date');
    }

    public function fetchOrThrow(string $key, string $value, ?string $userUuid = null): UnitDateBlock
    {
        $query = $this->block->newQuery()->where($key, $value);
        if ($userUuid !== null) {
            $query->where('user_uuid', $userUuid);
        }
        $block = $query->first();

        if (is_null($block)) {
            throw new NoUnitDateBlockFoundException();
        }

        return $block;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): UnitDateBlock
    {
        $data = GeneralHelper::unsetUnknownAndNullFields($payload, UnitDateBlock::DATA);

        return $this->block->newQuery()->create($data);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(UnitDateBlock $block, array $payload): bool
    {
        $data = GeneralHelper::unsetUnknownFields($payload, UnitDateBlock::DATA);

        return $block->update($data);
    }

    public function delete(UnitDateBlock $block): void
    {
        $block->delete();
    }

    public function blockOverlaps(string $unitUuid, Carbon $start, Carbon $end, ?string $exceptUuid): bool
    {
        $q = $this->block->newQuery()
            ->where('unit_uuid', $unitUuid)
            ->where('start_date', '<', $end->toDateString())
            ->where('end_date', '>', $start->toDateString());

        if ($exceptUuid !== null) {
            $q->where('uuid', '!=', $exceptUuid);
        }

        return $q->exists();
    }
}
