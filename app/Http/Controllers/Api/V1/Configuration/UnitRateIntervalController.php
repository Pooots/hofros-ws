<?php

namespace App\Http\Controllers\Api\V1\Configuration;

use App\Http\Controllers\Controller;
use App\Http\Repositories\UnitRateIntervalRepository;
use App\Http\Repositories\UnitRepository;
use App\Http\Requests\UnitRateInterval\ListUnitRateIntervalRequest;
use App\Http\Requests\UnitRateInterval\SaveUnitRateIntervalRequest;
use App\Http\Resources\UnitRateIntervalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class UnitRateIntervalController extends Controller
{
    public function __construct(
        protected UnitRateIntervalRepository $intervalRepository,
        protected UnitRepository $unitRepository,
    ) {
    }

    public function index(ListUnitRateIntervalRequest $request, string $uuid): JsonResponse
    {
        $unit = $this->unitRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);

        $intervals = $this->intervalRepository->getAll([
            'user_uuid' => $request->user()->uuid,
            'unit_uuid' => $unit->uuid,
        ])->get();

        return response()->json([
            'intervals' => UnitRateIntervalResource::collection($intervals)->resolve($request),
        ]);
    }

    public function store(SaveUnitRateIntervalRequest $request, string $uuid): JsonResponse
    {
        $unit = $this->unitRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);

        $days = $this->intervalRepository->normalizeDaysOfWeek($request->validated('daysOfWeek'));
        $dayPrices = $this->intervalRepository->normalizeDayPrices($request->validated('dayPrices'));
        $basePrice = $this->intervalRepository->deriveBasePrice($dayPrices);

        $row = $this->intervalRepository->create(
            $request->toModelPayload($request->user()->uuid, $unit->uuid, $days, $dayPrices, $basePrice)
        );

        return (new UnitRateIntervalResource($row))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveUnitRateIntervalRequest $request, string $uuid, string $intervalUuid): JsonResponse
    {
        $unit = $this->unitRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);
        $row = $this->intervalRepository->fetchOrThrow(
            'uuid',
            $intervalUuid,
            $request->user()->uuid,
            $unit->uuid
        );

        $days = $this->intervalRepository->normalizeDaysOfWeek($request->validated('daysOfWeek'));
        $dayPrices = $this->intervalRepository->normalizeDayPrices($request->validated('dayPrices'));
        $basePrice = $this->intervalRepository->deriveBasePrice($dayPrices);

        $this->intervalRepository->update(
            $row,
            $request->toModelPayload($request->user()->uuid, $unit->uuid, $days, $dayPrices, $basePrice)
        );

        return (new UnitRateIntervalResource($row->fresh()))->response();
    }

    public function destroy(string $uuid, string $intervalUuid): JsonResponse
    {
        $request = request();
        $unit = $this->unitRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);
        $row = $this->intervalRepository->fetchOrThrow(
            'uuid',
            $intervalUuid,
            $request->user()->uuid,
            $unit->uuid
        );

        $this->intervalRepository->delete($row);

        return response()->json(['message' => 'Interval deleted.']);
    }
}
