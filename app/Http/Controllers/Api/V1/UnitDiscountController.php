<?php

namespace App\Http\Controllers\Api\v1;

use App\Exceptions\BookingValidationException;
use App\Http\Controllers\Controller;
use App\Http\Repositories\UnitDiscountRepository;
use App\Http\Requests\UnitDiscount\CreateUnitDiscountRequest;
use App\Http\Requests\UnitDiscount\ListUnitDiscountRequest;
use App\Http\Requests\UnitDiscount\UpdateUnitDiscountRequest;
use App\Http\Resources\UnitDiscountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class UnitDiscountController extends Controller
{
    public function __construct(protected UnitDiscountRepository $discountRepository)
    {
    }

    public function index(ListUnitDiscountRequest $request): JsonResponse
    {
        $filters = array_merge($request->validated(), [
            'user_uuid' => $request->user()->uuid,
        ]);

        $discounts = $this->discountRepository->getAll($filters)->get();

        return response()->json([
            'unit_discounts' => UnitDiscountResource::collection($discounts)->resolve($request),
        ]);
    }

    public function store(CreateUnitDiscountRequest $request): JsonResponse
    {
        $discount = $this->discountRepository->create($request->toModelPayload());
        $discount->load('unit:uuid,name');

        return (new UnitDiscountResource($discount))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateUnitDiscountRequest $request, string $uuid): JsonResponse
    {
        $discount = $this->discountRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);
        $payload = $request->toModelPayload();

        $validFrom = array_key_exists('valid_from', $payload)
            ? $payload['valid_from']
            : $discount->valid_from?->format('Y-m-d');
        $validTo = array_key_exists('valid_to', $payload)
            ? $payload['valid_to']
            : $discount->valid_to?->format('Y-m-d');

        if ($validFrom !== null && $validTo !== null && $validTo < $validFrom) {
            throw new BookingValidationException('The valid_to must be a date after or equal to valid_from.');
        }

        $this->discountRepository->update($discount, $payload);
        $discount->refresh()->load('unit:uuid,name');

        return (new UnitDiscountResource($discount))->response();
    }

    public function destroy(string $uuid): JsonResponse
    {
        $request = request();
        $discount = $this->discountRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);
        $this->discountRepository->delete($discount);

        return response()->json(['message' => 'Unit discount deleted.']);
    }
}
