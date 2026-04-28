<?php

namespace App\Http\Repositories;

use App\Exceptions\NoPromoCodeFoundException;
use App\Helpers\GeneralHelper;
use App\Models\PromoCode;
use Illuminate\Contracts\Database\Eloquent\Builder;

class PromoCodeRepository
{
    public function __construct(protected PromoCode $promoCode)
    {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters): Builder
    {
        return $this->promoCode->newQuery()
            ->filters($filters)
            ->orderByDesc('created_at');
    }

    public function fetchOrThrow(string $key, string $value, ?string $userUuid = null): PromoCode
    {
        $query = $this->promoCode->newQuery()->where($key, $value);
        if ($userUuid !== null) {
            $query->where('user_uuid', $userUuid);
        }
        $promoCode = $query->first();

        if (is_null($promoCode)) {
            throw new NoPromoCodeFoundException();
        }

        return $promoCode;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): PromoCode
    {
        $data = GeneralHelper::unsetUnknownAndNullFields($payload, PromoCode::DATA);

        return $this->promoCode->newQuery()->create($data);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(PromoCode $promoCode, array $payload): bool
    {
        $data = GeneralHelper::unsetUnknownFields($payload, PromoCode::DATA);

        return $promoCode->update($data);
    }

    public function delete(PromoCode $promoCode): void
    {
        $promoCode->delete();
    }
}
