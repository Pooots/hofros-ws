<?php

namespace App\Http\Controllers\Api\v1;

use App\Exceptions\BookingValidationException;
use App\Http\Controllers\Controller;
use App\Http\Repositories\BookingRepository;
use App\Http\Repositories\UnitRepository;
use App\Http\Requests\Booking\CreateBookingRequest;
use App\Http\Requests\Booking\ListBookingRequest;
use App\Http\Requests\Booking\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Unit;
use App\Support\BookingStayConflict;
use App\Support\UnitStayPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function __construct(
        protected BookingRepository $bookingRepository,
        protected UnitRepository $unitRepository,
    ) {
    }

    public function index(ListBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $q = isset($validated['q']) ? trim((string) $validated['q']) : '';
        $status = $validated['status'] ?? 'all';
        $expandBatch = (bool) ($validated['expandBatch'] ?? false);

        $userUuid = $request->user()->uuid;

        $query = $this->bookingRepository->getAll([
            'user_uuid' => $userUuid,
            'status' => $status,
        ]);

        if (! $expandBatch) {
            $this->bookingRepository->applyPortalBatchListCollapseScope($query, $userUuid);
        }

        if ($q !== '') {
            $this->bookingRepository->applySearch($query, $userUuid, $q);
        }

        $shouldPaginate = $request->filled('page') || $request->filled('perPage');

        if (! $shouldPaginate) {
            $bookings = BookingResource::collection($query->get())->resolve($request);

            return response()->json(['bookings' => $bookings]);
        }

        $perPage = min(max((int) ($validated['perPage'] ?? 15), 1), 100);
        $page = max((int) ($validated['page'] ?? 1), 1);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $bookings = BookingResource::collection(collect($paginator->items()))->resolve($request);

        return response()->json([
            'bookings' => $bookings,
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $booking = $this->bookingRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid, true);

        return (new BookingResource($booking))->response();
    }

    public function store(CreateBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $unit = $this->unitRepository->fetchOrThrow('uuid', $validated['unitId'], $request->user()->uuid);

        $adults = (int) $validated['adults'];
        $children = (int) $validated['children'];
        if ($adults + $children > (int) $unit->max_guests) {
            throw new BookingValidationException('Guest count exceeds the maximum for this unit.');
        }

        $checkIn = Carbon::createFromFormat('Y-m-d', $validated['checkIn'])->startOfDay();
        $checkOut = Carbon::createFromFormat('Y-m-d', $validated['checkOut'])->startOfDay();

        if (array_key_exists('totalPrice', $validated) && $validated['totalPrice'] !== null) {
            $total = round((float) $validated['totalPrice'], 2);
        } else {
            $pricing = UnitStayPricing::computeForStay($unit, $checkIn, $checkOut);
            if ($pricing['error'] !== null) {
                throw new BookingValidationException((string) $pricing['error']);
            }
            $total = $pricing['total'];
        }

        $source = $validated['source'] ?? Booking::SOURCE_MANUAL;
        if (! is_string($source) || trim($source) === '') {
            $source = Booking::SOURCE_MANUAL;
        }

        $status = $validated['status'] ?? Booking::STATUS_PENDING;

        if (BookingStayConflict::hasOverlappingBooking($request->user()->uuid, $unit->uuid, $checkIn, $checkOut, null)) {
            throw new BookingValidationException('Those dates overlap an existing booking for this unit.');
        }
        if (BookingStayConflict::hasOverlappingBlock($request->user()->uuid, $unit->uuid, $checkIn, $checkOut)) {
            throw new BookingValidationException('Those dates are blocked for this unit.');
        }

        $booking = $this->bookingRepository->create([
            'user_uuid' => $request->user()->uuid,
            'unit_uuid' => $unit->uuid,
            'reference' => $this->bookingRepository->uniqueReference(),
            'guest_name' => $validated['guestName'],
            'guest_email' => $validated['guestEmail'],
            'guest_phone' => $validated['guestPhone'],
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'adults' => $adults,
            'children' => $children,
            'total_price' => $total,
            'currency' => $unit->currency,
            'source' => Str::lower(trim($source)),
            'status' => $status,
            'notes' => $validated['notes'] ?? null,
        ]);

        $booking->load($this->bookingRepository->unitEagerLoad());

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function availableUnits(Request $request, string $uuid): JsonResponse
    {
        $booking = $this->bookingRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);

        if (! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED], true)) {
            return response()->json(['units' => []]);
        }

        $template = $booking->unit;
        if ($template === null) {
            return response()->json(['units' => []]);
        }

        $checkIn = Carbon::parse($booking->check_in)->startOfDay();
        $checkOut = Carbon::parse($booking->check_out)->startOfDay();

        $available = $this->bookingRepository->getAvailableMatchingUnits(
            $request->user()->uuid,
            $template,
            $checkIn,
            $checkOut,
            $booking->uuid
        );

        return response()->json([
            'units' => $available
                ->map(static fn (Unit $u): array => [
                    'uuid' => $u->uuid,
                    'name' => $u->name,
                    'propertyName' => $u->property?->property_name,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function update(UpdateBookingRequest $request, string $uuid): JsonResponse
    {
        $booking = $this->bookingRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);
        $validated = $request->validated();
        unset($validated['uuid']);

        $batchId = $booking->portal_batch_id;
        if ($batchId !== null && $batchId !== '') {
            $onlyStatus = count($validated) === 1 && array_key_exists('status', $validated);
            if ($onlyStatus) {
                return $this->applyPortalBatchStatusChange($request, $booking, (string) $validated['status']);
            }
        }

        $payload = [];
        if (array_key_exists('unitId', $validated)) {
            $payload['unit_uuid'] = $validated['unitId'];
        }
        if (array_key_exists('guestName', $validated)) {
            $payload['guest_name'] = $validated['guestName'];
        }
        if (array_key_exists('guestEmail', $validated)) {
            $payload['guest_email'] = $validated['guestEmail'];
        }
        if (array_key_exists('guestPhone', $validated)) {
            $payload['guest_phone'] = $validated['guestPhone'];
        }
        if (array_key_exists('checkIn', $validated)) {
            $payload['check_in'] = $validated['checkIn'];
        }
        if (array_key_exists('checkOut', $validated)) {
            $payload['check_out'] = $validated['checkOut'];
        }
        if (array_key_exists('adults', $validated)) {
            $payload['adults'] = $validated['adults'];
        }
        if (array_key_exists('children', $validated)) {
            $payload['children'] = $validated['children'];
        }
        if (array_key_exists('source', $validated) && $validated['source'] !== null) {
            $payload['source'] = Str::lower(trim((string) $validated['source']));
        }
        if (array_key_exists('status', $validated)) {
            $payload['status'] = $validated['status'];
        }
        if (array_key_exists('notes', $validated)) {
            $payload['notes'] = $validated['notes'];
        }
        if (array_key_exists('totalPrice', $validated) && $validated['totalPrice'] !== null) {
            $payload['total_price'] = round((float) $validated['totalPrice'], 2);
        }

        $booking->fill($payload);

        $nextStatus = $payload['status'] ?? $booking->status;
        $this->validateBookingStatusTransition($request, $booking, $nextStatus);

        $unit = $this->unitRepository->fetchOrThrow('uuid', $booking->unit_uuid, $request->user()->uuid);

        $datesOrUnitChanged = array_key_exists('checkIn', $validated)
            || array_key_exists('checkOut', $validated)
            || array_key_exists('unitId', $validated);

        if (! (array_key_exists('totalPrice', $validated) && $validated['totalPrice'] !== null) && $datesOrUnitChanged) {
            $checkIn = Carbon::parse($booking->check_in)->startOfDay();
            $checkOut = Carbon::parse($booking->check_out)->startOfDay();
            $pricing = UnitStayPricing::computeForStay($unit, $checkIn, $checkOut);
            if ($pricing['error'] !== null) {
                throw new BookingValidationException((string) $pricing['error']);
            }
            $payload['currency'] = $unit->currency;
            $payload['total_price'] = $pricing['total'];
        }

        $this->bookingRepository->update($booking, $payload);

        $booking->refresh()->load($this->bookingRepository->unitEagerLoad());
        $booking->loadSum('payments', 'amount');

        return (new BookingResource($booking))->response();
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $booking = $this->bookingRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);
        $this->bookingRepository->delete($booking);

        return response()->json(['ok' => true]);
    }

    private function applyPortalBatchStatusChange(Request $request, Booking $booking, string $nextStatus): JsonResponse
    {
        $batch = $this->bookingRepository->batchSiblings($booking);

        foreach ($batch as $b) {
            $this->validateBookingStatusTransition($request, $b, $nextStatus);
        }

        DB::transaction(function () use ($batch, $nextStatus): void {
            foreach ($batch as $b) {
                $b->status = $nextStatus;
                $b->save();
            }
        });

        $booking->refresh();
        $booking->load($this->bookingRepository->unitEagerLoad());
        $booking->loadSum('payments', 'amount');

        return (new BookingResource($booking))->response();
    }

    private function validateBookingStatusTransition(Request $request, Booking $booking, string $nextStatus): void
    {
        if ($booking->check_out <= $booking->check_in) {
            throw new BookingValidationException('Check-out must be after check-in.');
        }

        $checkInCarbon = Carbon::parse($booking->check_in)->startOfDay();
        $checkOutCarbon = Carbon::parse($booking->check_out)->startOfDay();

        $unit = $this->unitRepository->fetchOrThrow('uuid', $booking->unit_uuid, $request->user()->uuid);
        if ($booking->adults + $booking->children > (int) $unit->max_guests) {
            throw new BookingValidationException('Guest count exceeds the maximum for this unit.');
        }

        if (in_array($nextStatus, [Booking::STATUS_ASSIGNED, Booking::STATUS_CHECKED_IN], true)) {
            if (BookingStayConflict::hasOverlappingBooking(
                $request->user()->uuid,
                $booking->unit_uuid,
                $checkInCarbon,
                $checkOutCarbon,
                $booking->uuid
            )) {
                throw new BookingValidationException('Those dates overlap another booking for this unit.');
            }
            if (BookingStayConflict::hasOverlappingBlock($request->user()->uuid, $booking->unit_uuid, $checkInCarbon, $checkOutCarbon)) {
                throw new BookingValidationException('Those dates are blocked for this unit.');
            }
        }

        if ($nextStatus === Booking::STATUS_ACCEPTED
            && ! $this->bookingRepository->hasAvailableMatchingUnit(
                $request->user()->uuid,
                $unit,
                $checkInCarbon,
                $checkOutCarbon,
                $booking->uuid
            )) {
            throw new BookingValidationException('No available unit matches this booking spec for the selected dates.');
        }
    }
}
