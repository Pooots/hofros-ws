<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Unit;
use App\Support\BookingStayConflict;
use App\Support\UnitStayPricing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', Rule::in([
                Booking::STATUS_PENDING,
                Booking::STATUS_ACCEPTED,
                Booking::STATUS_ASSIGNED,
                Booking::STATUS_CHECKED_IN,
                Booking::STATUS_CHECKED_OUT,
                Booking::STATUS_CANCELLED,
                'all',
            ])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'expandBatch' => ['sometimes', 'boolean'],
        ]);

        $q = isset($validated['q']) ? trim((string) $validated['q']) : '';
        $status = $validated['status'] ?? 'all';
        $expandBatch = (bool) ($validated['expandBatch'] ?? false);

        $userId = (int) $request->user()->id;

        $query = Booking::query()
            ->with($this->bookingUnitEagerLoad())
            ->where('user_id', $userId);
        if (! $expandBatch) {
            $this->applyPortalBatchListCollapseScope($query, $userId);
        }
        $query->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($status !== 'all' && $status !== '') {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($like, $userId): void {
                $w->where('guest_name', 'like', $like)
                    ->orWhere('guest_email', 'like', $like)
                    ->orWhere('guest_phone', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhereHas('unit', function ($u) use ($like): void {
                        $u->where('name', 'like', $like);
                    })
                    ->orWhere(function (Builder $w2) use ($like, $userId): void {
                        $w2->whereNotNull('bookings.portal_batch_id')
                            ->whereExists(function (QueryBuilder $sub) use ($like, $userId): void {
                                $sub->from('bookings as batch_sibling')
                                    ->whereColumn('batch_sibling.portal_batch_id', 'bookings.portal_batch_id')
                                    ->where('batch_sibling.user_id', '=', $userId)
                                    ->where(function (QueryBuilder $w3) use ($like): void {
                                        $w3->where('batch_sibling.guest_name', 'like', $like)
                                            ->orWhere('batch_sibling.guest_email', 'like', $like)
                                            ->orWhere('batch_sibling.guest_phone', 'like', $like)
                                            ->orWhere('batch_sibling.reference', 'like', $like)
                                            ->orWhereExists(function (QueryBuilder $sub2) use ($like): void {
                                                $sub2->selectRaw('1')
                                                    ->from('units')
                                                    ->whereColumn('units.id', 'batch_sibling.unit_id')
                                                    ->where('units.name', 'like', $like);
                                            });
                                    });
                            });
                    });
            });
        }

        $shouldPaginate = $request->filled('page') || $request->filled('perPage');

        if (! $shouldPaginate) {
            $bookings = $query->get()->map(fn (Booking $b) => $this->toPayload($b));

            return response()->json(['bookings' => $bookings]);
        }

        $perPage = min(max((int) ($validated['perPage'] ?? 15), 1), 100);
        $page = max((int) ($validated['page'] ?? 1), 1);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $bookings = collect($paginator->items())
            ->map(fn (Booking $b) => $this->toPayload($b))
            ->values();

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

    public function show(Request $request, int $id): JsonResponse
    {
        $booking = Booking::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->with($this->bookingUnitEagerLoad())
            ->withSum('payments', 'amount')
            ->firstOrFail();

        return response()->json($this->toPayload($booking));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unitId' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($q) => $q->where('user_id', $request->user()->id)),
            ],
            'guestName' => ['required', 'string', 'max:255'],
            'guestEmail' => ['required', 'string', 'email', 'max:255'],
            'guestPhone' => ['required', 'string', 'max:64'],
            'checkIn' => ['required', 'date_format:Y-m-d'],
            'checkOut' => ['required', 'date_format:Y-m-d', 'after:checkIn'],
            'adults' => ['required', 'integer', 'min:1', 'max:500'],
            'children' => ['required', 'integer', 'min:0', 'max:500'],
            'source' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'string', Rule::in([
                Booking::STATUS_PENDING,
                Booking::STATUS_ACCEPTED,
                Booking::STATUS_ASSIGNED,
                Booking::STATUS_CHECKED_IN,
                Booking::STATUS_CHECKED_OUT,
                Booking::STATUS_CANCELLED,
            ])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'totalPrice' => ['nullable', 'numeric', 'min:0'],
        ]);

        $unit = Unit::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($validated['unitId'])
            ->firstOrFail();

        $adults = (int) $validated['adults'];
        $children = (int) $validated['children'];
        if ($adults + $children > (int) $unit->max_guests) {
            return response()->json(['message' => 'Guest count exceeds the maximum for this unit.'], 422);
        }

        $checkIn = Carbon::createFromFormat('Y-m-d', $validated['checkIn'])->startOfDay();
        $checkOut = Carbon::createFromFormat('Y-m-d', $validated['checkOut'])->startOfDay();

        if (array_key_exists('totalPrice', $validated) && $validated['totalPrice'] !== null) {
            $total = round((float) $validated['totalPrice'], 2);
        } else {
            $pricing = UnitStayPricing::computeForStay($unit, $checkIn, $checkOut);
            if ($pricing['error'] !== null) {
                return response()->json(['message' => $pricing['error']], 422);
            }
            $total = $pricing['total'];
        }

        $source = $validated['source'] ?? Booking::SOURCE_MANUAL;
        if (! is_string($source) || trim($source) === '') {
            $source = Booking::SOURCE_MANUAL;
        }

        $status = $validated['status'] ?? Booking::STATUS_PENDING;

        if (BookingStayConflict::hasOverlappingBooking($request->user()->id, $unit->id, $checkIn, $checkOut, null)) {
            return response()->json(['message' => 'Those dates overlap an existing booking for this unit.'], 422);
        }
        if (BookingStayConflict::hasOverlappingBlock($request->user()->id, $unit->id, $checkIn, $checkOut)) {
            return response()->json(['message' => 'Those dates are blocked for this unit.'], 422);
        }

        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'unit_id' => $unit->id,
            'reference' => $this->uniqueReference(),
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

        return response()->json($this->toPayload($booking->load($this->bookingUnitEagerLoad())), 201);
    }

    public function availableUnits(Request $request, int $id): JsonResponse
    {
        $booking = Booking::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->with($this->bookingUnitEagerLoad())
            ->firstOrFail();

        if (! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED], true)) {
            return response()->json(['units' => []]);
        }

        $template = $booking->unit;
        if ($template === null) {
            return response()->json(['units' => []]);
        }

        $checkIn = Carbon::parse($booking->check_in)->startOfDay();
        $checkOut = Carbon::parse($booking->check_out)->startOfDay();

        $available = $this->getAvailableMatchingUnits(
            $request->user()->id,
            $template,
            $checkIn,
            $checkOut,
            (int) $booking->id
        );

        return response()->json([
            'units' => $available
                ->map(static fn (Unit $u): array => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'propertyName' => $u->property?->property_name,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $booking = Booking::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $validated = $request->validate([
            'unitId' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($q) => $q->where('user_id', $request->user()->id)),
            ],
            'guestName' => ['sometimes', 'required', 'string', 'max:255'],
            'guestEmail' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'guestPhone' => ['sometimes', 'required', 'string', 'max:64'],
            'checkIn' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'checkOut' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'adults' => ['sometimes', 'required', 'integer', 'min:1', 'max:500'],
            'children' => ['sometimes', 'required', 'integer', 'min:0', 'max:500'],
            'source' => ['sometimes', 'nullable', 'string', 'max:32'],
            'status' => ['sometimes', 'required', 'string', Rule::in([
                Booking::STATUS_PENDING,
                Booking::STATUS_ACCEPTED,
                Booking::STATUS_ASSIGNED,
                Booking::STATUS_CHECKED_IN,
                Booking::STATUS_CHECKED_OUT,
                Booking::STATUS_CANCELLED,
            ])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'totalPrice' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $batchId = $booking->portal_batch_id;
        if ($batchId !== null && $batchId !== '') {
            $onlyStatus = count($validated) === 1 && array_key_exists('status', $validated);
            // Keep explicit batch status action, but allow per-booking assign (status + unitId)
            // so multi-unit transactions can be assigned one by one with different unit choices.
            if ($onlyStatus) {
                return $this->applyPortalBatchStatusChange($request, $booking, (string) $validated['status']);
            }
        }

        if (array_key_exists('unitId', $validated)) {
            $booking->unit_id = $validated['unitId'];
        }
        if (array_key_exists('guestName', $validated)) {
            $booking->guest_name = $validated['guestName'];
        }
        if (array_key_exists('guestEmail', $validated)) {
            $booking->guest_email = $validated['guestEmail'];
        }
        if (array_key_exists('guestPhone', $validated)) {
            $booking->guest_phone = $validated['guestPhone'];
        }
        if (array_key_exists('checkIn', $validated)) {
            $booking->check_in = $validated['checkIn'];
        }
        if (array_key_exists('checkOut', $validated)) {
            $booking->check_out = $validated['checkOut'];
        }
        if (array_key_exists('adults', $validated)) {
            $booking->adults = $validated['adults'];
        }
        if (array_key_exists('children', $validated)) {
            $booking->children = $validated['children'];
        }
        if (array_key_exists('source', $validated) && $validated['source'] !== null) {
            $booking->source = Str::lower(trim((string) $validated['source']));
        }
        if (array_key_exists('status', $validated)) {
            $booking->status = $validated['status'];
        }
        if (array_key_exists('notes', $validated)) {
            $booking->notes = $validated['notes'];
        }
        if (array_key_exists('totalPrice', $validated)) {
            $booking->total_price = $validated['totalPrice'] === null
                ? $booking->total_price
                : round((float) $validated['totalPrice'], 2);
        }

        $nextStatus = $validated['status'] ?? $booking->status;
        $transitionError = $this->validateBookingStatusTransition($request, $booking, $nextStatus);
        if ($transitionError !== null) {
            return $transitionError;
        }

        $unit = Unit::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($booking->unit_id)
            ->firstOrFail();

        $datesOrUnitChanged = array_key_exists('checkIn', $validated)
            || array_key_exists('checkOut', $validated)
            || array_key_exists('unitId', $validated);

        if (array_key_exists('totalPrice', $validated) && $validated['totalPrice'] !== null) {
            $booking->total_price = round((float) $validated['totalPrice'], 2);
        } elseif ($datesOrUnitChanged) {
            $checkIn = Carbon::parse($booking->check_in)->startOfDay();
            $checkOut = Carbon::parse($booking->check_out)->startOfDay();
            $pricing = UnitStayPricing::computeForStay($unit, $checkIn, $checkOut);
            if ($pricing['error'] !== null) {
                return response()->json(['message' => $pricing['error']], 422);
            }
            $booking->currency = $unit->currency;
            $booking->total_price = $pricing['total'];
        }

        $booking->save();

        $booking->load($this->bookingUnitEagerLoad());
        $booking->loadSum('payments', 'amount');

        return response()->json($this->toPayload($booking));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $booking = Booking::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $booking->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Guest booking list: one row per direct-portal multi-unit submission (shared {@see Booking::$portal_batch_id}).
     */
    private function applyPortalBatchListCollapseScope(Builder $query, int $userId): void
    {
        $query->where(function (Builder $w) use ($userId): void {
            $w->whereNull('portal_batch_id')
                ->orWhereIn(
                    'id',
                    Booking::query()
                        ->selectRaw('MIN(id)')
                        ->where('user_id', $userId)
                        ->whereNotNull('portal_batch_id')
                        ->groupBy('portal_batch_id'),
                );
        });
    }

    private function applyPortalBatchStatusChange(Request $request, Booking $booking, string $nextStatus): JsonResponse
    {
        $batchId = (string) $booking->portal_batch_id;
        $batch = Booking::query()
            ->where('user_id', $request->user()->id)
            ->where('portal_batch_id', $batchId)
            ->with($this->bookingUnitEagerLoad())
            ->orderBy('id')
            ->get();

        foreach ($batch as $b) {
            $err = $this->validateBookingStatusTransition($request, $b, $nextStatus);
            if ($err !== null) {
                return $err;
            }
        }

        DB::transaction(function () use ($batch, $nextStatus): void {
            foreach ($batch as $b) {
                $b->status = $nextStatus;
                $b->save();
            }
        });

        $booking->refresh();
        $booking->load($this->bookingUnitEagerLoad());
        $booking->loadSum('payments', 'amount');

        return response()->json($this->toPayload($booking));
    }

    private function validateBookingStatusTransition(Request $request, Booking $booking, string $nextStatus): ?JsonResponse
    {
        if ($booking->check_out <= $booking->check_in) {
            return response()->json(['message' => 'Check-out must be after check-in.'], 422);
        }

        $checkInCarbon = Carbon::parse($booking->check_in)->startOfDay();
        $checkOutCarbon = Carbon::parse($booking->check_out)->startOfDay();

        $unit = Unit::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($booking->unit_id)
            ->first();
        if ($unit === null) {
            return response()->json(['message' => 'Unit not found.'], 422);
        }
        if ($booking->adults + $booking->children > (int) $unit->max_guests) {
            return response()->json(['message' => 'Guest count exceeds the maximum for this unit.'], 422);
        }

        if (in_array($nextStatus, [Booking::STATUS_ASSIGNED, Booking::STATUS_CHECKED_IN], true)) {
            if (BookingStayConflict::hasOverlappingBooking(
                $request->user()->id,
                (int) $booking->unit_id,
                $checkInCarbon,
                $checkOutCarbon,
                (int) $booking->id
            )) {
                return response()->json(['message' => 'Those dates overlap another booking for this unit.'], 422);
            }
            if (BookingStayConflict::hasOverlappingBlock($request->user()->id, (int) $booking->unit_id, $checkInCarbon, $checkOutCarbon)) {
                return response()->json(['message' => 'Those dates are blocked for this unit.'], 422);
            }
        }

        if ($nextStatus === Booking::STATUS_ACCEPTED
            && ! $this->hasAvailableMatchingUnit(
                $request->user()->id,
                $unit,
                $checkInCarbon,
                $checkOutCarbon,
                (int) $booking->id
            )) {
            return response()->json([
                'message' => 'No available unit matches this booking spec for the selected dates.',
            ], 422);
        }

        return null;
    }

    /**
     * @return Collection<int, Unit>
     */
    private function getAvailableMatchingUnits(
        int $userId,
        Unit $template,
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $exceptBookingId
    ): Collection {
        $candidates = Unit::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('property_id', $template->property_id)
            ->where('max_guests', $template->max_guests)
            ->where('bedrooms', $template->bedrooms)
            ->where('beds', $template->beds)
            ->where(static function (Builder $q) use ($template): void {
                if ($template->type === null) {
                    $q->whereNull('type');
                } else {
                    $q->where('type', $template->type);
                }
            })
            ->with([
                'property' => static function ($q): void {
                    $q->select('id', 'property_name');
                },
            ])
            ->orderBy('id')
            ->get(['id', 'name', 'property_id']);

        return $candidates->filter(function (Unit $candidate) use ($userId, $checkIn, $checkOut, $exceptBookingId): bool {
            $unitId = (int) $candidate->id;
            if (BookingStayConflict::hasOverlappingBooking($userId, $unitId, $checkIn, $checkOut, $exceptBookingId)) {
                return false;
            }
            if (BookingStayConflict::hasOverlappingBlock($userId, $unitId, $checkIn, $checkOut)) {
                return false;
            }

            return true;
        })->values();
    }

    private function hasAvailableMatchingUnit(
        int $userId,
        Unit $template,
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $exceptBookingId
    ): bool {
        return $this->getAvailableMatchingUnits($userId, $template, $checkIn, $checkOut, $exceptBookingId)->isNotEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingUnitEagerLoad(): array
    {
        return [
            'unit' => static function ($q): void {
                $q->select(
                    'id',
                    'property_id',
                    'name',
                    'type',
                    'max_guests',
                    'bedrooms',
                    'beds',
                    'price_per_night',
                    'currency',
                    'status'
                )->with([
                    'property' => static function ($q2): void {
                        $q2->select('id', 'property_name');
                    },
                ]);
            },
        ];
    }

    private function uniqueReference(): string
    {
        for ($i = 0; $i < 12; $i++) {
            $candidate = 'HFR-'.Str::upper(Str::random(8));
            if (! Booking::query()->where('reference', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'HFR-'.Str::upper(Str::uuid()->toString());
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(Booking $booking): array
    {
        $unit = $booking->unit;

        $payload = [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'guestName' => $booking->guest_name,
            'guestEmail' => $booking->guest_email,
            'guestPhone' => $booking->guest_phone,
            'unitId' => $booking->unit_id,
            'propertyId' => $unit?->property_id !== null ? (int) $unit->property_id : null,
            'unitName' => $unit?->name,
            'accommodationName' => $unit?->property?->property_name,
            'unitType' => $unit?->type,
            'beds' => $unit?->beds,
            'bedrooms' => $unit?->bedrooms,
            'maxGuests' => $unit?->max_guests,
            'checkIn' => $booking->check_in?->format('Y-m-d'),
            'checkOut' => $booking->check_out?->format('Y-m-d'),
            'adults' => $booking->adults,
            'children' => $booking->children,
            'totalPrice' => (float) $booking->total_price,
            'currency' => $booking->currency,
            'source' => $booking->source,
            'status' => $booking->status,
            'notes' => $booking->notes,
            'createdAt' => $booking->created_at?->toIso8601String(),
            'updatedAt' => $booking->updated_at?->toIso8601String(),
        ];

        if (array_key_exists('payments_sum_amount', $booking->getAttributes())) {
            $total = round((float) $booking->total_price, 2);
            $paid = round((float) ($booking->payments_sum_amount ?? 0), 2);
            $payload['paidTotal'] = $paid;
            $payload['balanceDue'] = round(max(0, $total - $paid), 2);
        }

        $this->appendPortalBatchFields($booking, $payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function appendPortalBatchFields(Booking $booking, array &$payload): void
    {
        $batchId = $booking->portal_batch_id;
        if ($batchId === null || $batchId === '') {
            $payload['portalBatchId'] = null;
            $payload['batchBookings'] = null;
            $payload['batchTotalPrice'] = (float) $booking->total_price;
            $payload['batchUnitNames'] = null;

            return;
        }

        $rows = Booking::query()
            ->where('user_id', $booking->user_id)
            ->where('portal_batch_id', $batchId)
            ->with(['unit:id,name'])
            ->orderBy('id')
            ->get();

        $lines = $rows->map(static function (Booking $b): array {
            return [
                'id' => $b->id,
                'reference' => $b->reference,
                'unitName' => $b->unit?->name,
                'totalPrice' => (float) $b->total_price,
            ];
        })->values()->all();

        $sum = round((float) $rows->sum(static fn (Booking $b): float => (float) $b->total_price), 2);
        $names = $rows->map(static fn (Booking $b): string => (string) ($b->unit?->name ?? 'Unit'))->values()->all();

        $payload['portalBatchId'] = (string) $batchId;
        $payload['batchBookings'] = $lines;
        $payload['batchTotalPrice'] = $sum;
        $payload['batchUnitNames'] = $names;
    }
}
