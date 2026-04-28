<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BookingValidationException;
use App\Http\Controllers\Controller;
use App\Http\Repositories\UnitDateBlockRepository;
use App\Http\Requests\UnitDateBlock\CreateUnitDateBlockRequest;
use App\Http\Requests\UnitDateBlock\ListUnitDateBlockRequest;
use App\Http\Requests\UnitDateBlock\UpdateUnitDateBlockRequest;
use App\Http\Resources\UnitDateBlockResource;
use App\Support\BookingStayConflict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class UnitDateBlockController extends Controller
{
    public function __construct(protected UnitDateBlockRepository $blockRepository)
    {
    }

    public function index(ListUnitDateBlockRequest $request): JsonResponse
    {
        $filters = array_merge($request->validated(), [
            'user_uuid' => $request->user()->uuid,
        ]);

        $rows = $this->blockRepository->getAll($filters)->get();

        return response()->json([
            'blocks' => UnitDateBlockResource::collection($rows)->resolve($request),
        ]);
    }

    public function store(CreateUnitDateBlockRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $start = Carbon::createFromFormat('Y-m-d', $validated['startDate'])->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $validated['endDate'])->startOfDay();

        if ($this->blockRepository->blockOverlaps($validated['unit_uuid'], $start, $end, null)) {
            throw new BookingValidationException('This range overlaps another blocked period for the same unit.');
        }

        if (BookingStayConflict::blockOverlapsFirmBooking($request->user()->uuid, $validated['unit_uuid'], $start, $end)) {
            throw new BookingValidationException(
                'This range overlaps an existing reservation on this unit. Adjust the booking or pick dates that do not cover booked nights.'
            );
        }

        $block = $this->blockRepository->create([
            'user_uuid' => $request->user()->uuid,
            'unit_uuid' => $validated['unit_uuid'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'label' => $validated['label'],
            'notes' => $validated['notes'] ?? null,
        ]);
        $block->load(['unit:uuid,name,type']);

        return (new UnitDateBlockResource($block))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateUnitDateBlockRequest $request, string $uuid): JsonResponse
    {
        $block = $this->blockRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);
        $validated = $request->validated();

        $payload = [];
        if (array_key_exists('unit_uuid', $validated)) {
            $payload['unit_uuid'] = $validated['unit_uuid'];
        }
        if (array_key_exists('startDate', $validated)) {
            $payload['start_date'] = $validated['startDate'];
        }
        if (array_key_exists('endDate', $validated)) {
            $payload['end_date'] = $validated['endDate'];
        }
        if (array_key_exists('label', $validated)) {
            $payload['label'] = $validated['label'];
        }
        if (array_key_exists('notes', $validated)) {
            $payload['notes'] = $validated['notes'];
        }

        $resolvedUnitUuid = $payload['unit_uuid'] ?? $block->unit_uuid;
        $start = Carbon::parse($payload['start_date'] ?? $block->start_date)->startOfDay();
        $end = Carbon::parse($payload['end_date'] ?? $block->end_date)->startOfDay();

        if ($end->lte($start)) {
            throw new BookingValidationException('End date must be after start date (end is exclusive, same as check-out).');
        }

        if ($this->blockRepository->blockOverlaps($resolvedUnitUuid, $start, $end, $block->uuid)) {
            throw new BookingValidationException('This range overlaps another blocked period for the same unit.');
        }

        if (BookingStayConflict::blockOverlapsFirmBooking($request->user()->uuid, $resolvedUnitUuid, $start, $end)) {
            throw new BookingValidationException(
                'This range overlaps an existing reservation on this unit. Adjust the booking or pick dates that do not cover booked nights.'
            );
        }

        $this->blockRepository->update($block, $payload);
        $block->refresh()->load(['unit:uuid,name,type']);

        return (new UnitDateBlockResource($block))->response();
    }

    public function destroy(string $uuid): JsonResponse
    {
        $request = request();
        $block = $this->blockRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);
        $this->blockRepository->delete($block);

        return response()->json(['ok' => true]);
    }
}
