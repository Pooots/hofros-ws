<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Repositories\PromoCodeRepository;
use App\Http\Requests\PromoCode\CreatePromoCodeRequest;
use App\Http\Requests\PromoCode\ListPromoCodeRequest;
use App\Http\Requests\PromoCode\UpdatePromoCodeRequest;
use App\Http\Resources\PromoCodeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PromoCodeController extends Controller
{
    public function __construct(protected PromoCodeRepository $promoCodeRepository)
    {
    }

    public function index(ListPromoCodeRequest $request): JsonResponse
    {
        $filters = array_merge($request->validated(), [
            'user_uuid' => $request->user()->uuid,
        ]);

        $promoCodes = $this->promoCodeRepository->getAll($filters)->get();

        return response()->json([
            'promoCodes' => PromoCodeResource::collection($promoCodes)->resolve($request),
        ]);
    }

    public function store(CreatePromoCodeRequest $request): JsonResponse
    {
        $promoCode = $this->promoCodeRepository->create($request->toModelPayload());

        return (new PromoCodeResource($promoCode))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdatePromoCodeRequest $request, string $uuid): JsonResponse
    {
        $promoCode = $this->promoCodeRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);
        $this->promoCodeRepository->update($promoCode, $request->toModelPayload());

        return (new PromoCodeResource($promoCode->fresh()))->response();
    }

    public function destroy(string $uuid): JsonResponse
    {
        $request = request();
        $promoCode = $this->promoCodeRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);
        $this->promoCodeRepository->delete($promoCode);

        return response()->json(['message' => 'Promo code deleted.']);
    }
}
