<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\UnitDateBlock;
use App\Support\BookingStayConflict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class UnitDateBlockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'unitId' => ['nullable', 'integer'],
        ]);

        $query = UnitDateBlock::query()
            ->where('user_id', $request->user()->id)
            ->with(['unit:id,name,type'])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if (isset($validated['unitId'])) {
            $query->where('unit_id', $validated['unitId']);
        }

        if (! empty($validated['from'])) {
            $from = $validated['from'];
            $query->where('end_date', '>', $from);
        }
        if (! empty($validated['to'])) {
            $to = $validated['to'];
            $query->where('start_date', '<', $to);
        }

        $rows = $query->get()->map(fn (UnitDateBlock $r) => $this->toPayload($r));

        return response()->json(['blocks' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unitId' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($q) => $q->where('user_id', $request->user()->id)),
            ],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after:startDate'],
            'label' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $start = Carbon::createFromFormat('Y-m-d', $validated['startDate'])->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $validated['endDate'])->startOfDay();

        if ($this->blockOverlaps((int) $validated['unitId'], $start, $end, null)) {
            return response()->json(['message' => 'This range overlaps another blocked period for the same unit.'], 422);
        }

        if (BookingStayConflict::blockOverlapsFirmBooking($request->user()->id, (int) $validated['unitId'], $start, $end)) {
            return response()->json([
                'message' => 'This range overlaps an existing reservation on this unit. Adjust the booking or pick dates that do not cover booked nights.',
            ], 422);
        }

        $block = UnitDateBlock::create([
            'user_id' => $request->user()->id,
            'unit_id' => (int) $validated['unitId'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'label' => $validated['label'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($this->toPayload($block->load(['unit:id,name,type'])), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $block = UnitDateBlock::query()
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
            'startDate' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'endDate' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        if (array_key_exists('unitId', $validated)) {
            $block->unit_id = (int) $validated['unitId'];
        }
        if (array_key_exists('startDate', $validated)) {
            $block->start_date = $validated['startDate'];
        }
        if (array_key_exists('endDate', $validated)) {
            $block->end_date = $validated['endDate'];
        }
        if (array_key_exists('label', $validated)) {
            $block->label = $validated['label'];
        }
        if (array_key_exists('notes', $validated)) {
            $block->notes = $validated['notes'];
        }

        $start = Carbon::parse($block->start_date)->startOfDay();
        $end = Carbon::parse($block->end_date)->startOfDay();
        if ($end->lte($start)) {
            return response()->json(['message' => 'End date must be after start date (end is exclusive, same as check-out).'], 422);
        }

        if ($this->blockOverlaps((int) $block->unit_id, $start, $end, $block->id)) {
            return response()->json(['message' => 'This range overlaps another blocked period for the same unit.'], 422);
        }

        if (BookingStayConflict::blockOverlapsFirmBooking($request->user()->id, (int) $block->unit_id, $start, $end)) {
            return response()->json([
                'message' => 'This range overlaps an existing reservation on this unit. Adjust the booking or pick dates that do not cover booked nights.',
            ], 422);
        }

        $block->save();

        return response()->json($this->toPayload($block->load(['unit:id,name,type'])));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $block = UnitDateBlock::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $block->delete();

        return response()->json(['ok' => true]);
    }

    private function blockOverlaps(int $unitId, Carbon $start, Carbon $end, ?int $exceptId): bool
    {
        $q = UnitDateBlock::query()
            ->where('unit_id', $unitId)
            ->where('start_date', '<', $end->toDateString())
            ->where('end_date', '>', $start->toDateString());

        if ($exceptId !== null) {
            $q->where('id', '!=', $exceptId);
        }

        return $q->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(UnitDateBlock $block): array
    {
        $unit = $block->unit;

        return [
            'id' => $block->id,
            'unitId' => $block->unit_id,
            'unitName' => $unit?->name,
            'unitType' => $unit?->type,
            'startDate' => $block->start_date?->format('Y-m-d'),
            'endDate' => $block->end_date?->format('Y-m-d'),
            'label' => $block->label,
            'notes' => $block->notes,
            'createdAt' => $block->created_at?->toIso8601String(),
            'updatedAt' => $block->updated_at?->toIso8601String(),
        ];
    }
}
