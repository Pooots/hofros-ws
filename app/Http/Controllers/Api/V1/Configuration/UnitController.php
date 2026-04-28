<?php

namespace App\Http\Controllers\Api\v1\Configuration;

use App\Http\Controllers\Controller;
use App\Http\Repositories\UnitRepository;
use App\Http\Requests\Unit\CreateUnitRequest;
use App\Http\Requests\Unit\ListUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Http\Requests\Unit\UpdateUnitWeekScheduleRequest;
use App\Http\Requests\Unit\UploadUnitImagesRequest;
use App\Http\Resources\UnitResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class UnitController extends Controller
{
    public function __construct(protected UnitRepository $unitRepository)
    {
    }

    public function index(ListUnitRequest $request): JsonResponse
    {
        $filters = array_merge($request->validated(), [
            'user_uuid' => $request->user()->uuid,
        ]);

        $units = $this->unitRepository->getAll($filters)->get();

        return response()->json([
            'units' => UnitResource::collection($units)->resolve($request),
        ]);
    }

    public function store(CreateUnitRequest $request): JsonResponse
    {
        $fallbackCurrency = $this->unitRepository->resolvePropertyCurrency(
            $request->user()->uuid,
            $request->validated('propertyId')
        );

        $unit = $this->unitRepository->create($request->toModelPayload($fallbackCurrency));
        $unit->load('property:uuid,property_name');

        return (new UnitResource($unit))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateUnitRequest $request, string $uuid): JsonResponse
    {
        $unit = $this->unitRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);

        $fallbackCurrency = null;
        if ($request->has('currency') && empty($request->input('currency'))) {
            $propertyUuid = $request->input('propertyId') ?? $unit->property_uuid;
            $fallbackCurrency = $this->unitRepository->resolvePropertyCurrency(
                $request->user()->uuid,
                $propertyUuid
            );
        }

        $payload = $request->toModelPayload($fallbackCurrency);
        $this->unitRepository->update($unit, $payload);
        $unit->refresh()->load('property:uuid,property_name');

        return (new UnitResource($unit))->response();
    }

    public function uploadImages(UploadUnitImagesRequest $request, string $uuid): JsonResponse
    {
        $unit = $this->unitRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);

        /** @var array<int, string> $existing */
        $existing = is_array($unit->images) ? $unit->images : [];
        $files = $request->file('images', []);

        $merged = $existing;
        foreach ($files as $file) {
            if (count($merged) >= 20) {
                break;
            }
            $path = $file->store("units/{$request->user()->uuid}/{$unit->uuid}", 'public');
            $merged[] = '/storage/' . str_replace('\\', '/', $path);
        }

        $this->unitRepository->update($unit, ['images' => $merged]);
        $unit->refresh()->load('property:uuid,property_name');

        return (new UnitResource($unit))->response();
    }

    public function updateWeekSchedule(UpdateUnitWeekScheduleRequest $request, string $uuid): JsonResponse
    {
        $unit = $this->unitRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);

        $this->unitRepository->update($unit, [
            'week_schedule' => $request->validated('weekSchedule'),
        ]);
        $unit->refresh()->load('property:uuid,property_name');

        return (new UnitResource($unit))->response();
    }

    public function destroy(string $uuid): JsonResponse
    {
        $request = request();
        $unit = $this->unitRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);
        $this->unitRepository->delete($unit);

        return response()->json(['message' => 'Unit deleted.']);
    }
}
