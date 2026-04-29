<?php

namespace App\Http\Controllers\Api\V1\Configuration;

use App\Exceptions\NoPropertyFoundException;
use App\Http\Controllers\Controller;
use App\Http\Repositories\PropertyRepository;
use App\Http\Requests\Property\CreatePropertyRequest;
use App\Http\Requests\Property\ListPropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Http\Resources\PropertyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PropertyController extends Controller
{
    public function __construct(protected PropertyRepository $propertyRepository)
    {
    }

    public function index(ListPropertyRequest $request): JsonResponse
    {
        $filters = array_merge($request->validated(), [
            'user_uuid' => $request->user()->uuid,
        ]);

        $properties = $this->propertyRepository->getAll($filters)->get();

        return response()->json([
            'properties' => PropertyResource::collection($properties)->resolve($request),
        ]);
    }

    public function store(CreatePropertyRequest $request): JsonResponse
    {
        $property = $this->propertyRepository->create($request->toModelPayload());

        return (new PropertyResource($property))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdatePropertyRequest $request, string $uuid): JsonResponse
    {
        $property = $this->propertyRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);
        $this->propertyRepository->update($property, $request->toModelPayload());

        return (new PropertyResource($property->fresh()))->response();
    }

    public function destroy(string $uuid): JsonResponse|Response
    {
        $request = request();
        $property = $this->propertyRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);
        $this->propertyRepository->delete($property);

        return response()->json(['message' => 'Property deleted.']);
    }
}
