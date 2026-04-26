<?php

namespace App\Http\Controllers\Api\V1\Configuration;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $units = Unit::query()
            ->with(['property:id,property_name'])
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Unit $unit) => $this->toPayload($unit));

        return response()->json(['units' => $units]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'propertyId' => [
                'required',
                'integer',
                Rule::exists('properties', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['string', 'max:2048'],
            'type' => ['nullable', 'string', 'max:64'],
            'maxGuests' => ['required', 'integer', 'min:1', 'max:500'],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:200'],
            'beds' => ['required', 'integer', 'min:0', 'max:500'],
            'pricePerNight' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:16'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $propertyCurrency = Property::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($validated['propertyId'])
            ->value('currency') ?? 'PHP';

        $unit = Unit::create([
            'user_id' => $request->user()->id,
            'property_id' => $validated['propertyId'],
            'name' => $validated['name'],
            'details' => $validated['details'] ?? null,
            'description' => $validated['description'] ?? null,
            'images' => $validated['images'] ?? [],
            'type' => $validated['type'] ?? null,
            'max_guests' => $validated['maxGuests'],
            'bedrooms' => $validated['bedrooms'],
            'beds' => $validated['beds'],
            'price_per_night' => $validated['pricePerNight'] ?? 0,
            'currency' => $validated['currency'] ?? $propertyCurrency,
            'status' => $validated['status'],
            'week_schedule' => Unit::defaultWeekSchedule(),
        ]);

        return response()->json($this->toPayload($unit->load('property:id,property_name')), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $unit = Unit::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $validated = $request->validate([
            'propertyId' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('properties', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'details' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'images' => ['sometimes', 'nullable', 'array', 'max:20'],
            'images.*' => ['string', 'max:2048'],
            'type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'maxGuests' => ['sometimes', 'required', 'integer', 'min:1', 'max:500'],
            'bedrooms' => ['sometimes', 'required', 'integer', 'min:0', 'max:200'],
            'beds' => ['sometimes', 'required', 'integer', 'min:0', 'max:500'],
            'pricePerNight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:16'],
            'status' => ['sometimes', 'required', 'string', 'in:active,inactive'],
        ]);

        if (array_key_exists('propertyId', $validated)) {
            $unit->property_id = $validated['propertyId'];
        }
        if (array_key_exists('name', $validated)) {
            $unit->name = $validated['name'];
        }
        if (array_key_exists('details', $validated)) {
            $unit->details = $validated['details'];
        }
        if (array_key_exists('description', $validated)) {
            $unit->description = $validated['description'];
        }
        if (array_key_exists('images', $validated)) {
            $unit->images = $validated['images'];
        }
        if (array_key_exists('type', $validated)) {
            $unit->type = $validated['type'];
        }
        if (array_key_exists('maxGuests', $validated)) {
            $unit->max_guests = $validated['maxGuests'];
        }
        if (array_key_exists('bedrooms', $validated)) {
            $unit->bedrooms = $validated['bedrooms'];
        }
        if (array_key_exists('beds', $validated)) {
            $unit->beds = $validated['beds'];
        }
        if (array_key_exists('pricePerNight', $validated)) {
            $unit->price_per_night = $validated['pricePerNight'] ?? 0;
        }
        if (array_key_exists('currency', $validated)) {
            $propertyCurrency = Property::query()
                ->where('user_id', $request->user()->id)
                ->whereKey($unit->property_id)
                ->value('currency') ?? 'PHP';
            $unit->currency = $validated['currency'] ?? $propertyCurrency;
        }
        if (array_key_exists('status', $validated)) {
            $unit->status = $validated['status'];
        }
        $unit->save();

        return response()->json($this->toPayload($unit->fresh()->load('property:id,property_name')));
    }

    public function uploadImages(Request $request, int $id): JsonResponse
    {
        $unit = Unit::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['file', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ]);

        /** @var array<int, string> $existing */
        $existing = is_array($unit->images) ? $unit->images : [];
        $files = $request->file('images', []);

        $merged = $existing;
        foreach ($files as $file) {
            if (count($merged) >= 20) {
                break;
            }
            $path = $file->store("units/{$request->user()->id}/{$unit->id}", 'public');
            $merged[] = '/storage/'.str_replace('\\', '/', $path);
        }

        $unit->images = $merged;
        $unit->save();

        return response()->json($this->toPayload($unit->fresh()->load('property:id,property_name')));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $unit = Unit::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $unit->delete();

        return response()->json(['message' => 'Unit deleted.']);
    }

    public function updateWeekSchedule(Request $request, int $id): JsonResponse
    {
        $unit = Unit::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $validated = $request->validate([
            'weekSchedule' => ['required', 'array'],
            'weekSchedule.mon' => ['required', 'boolean'],
            'weekSchedule.tue' => ['required', 'boolean'],
            'weekSchedule.wed' => ['required', 'boolean'],
            'weekSchedule.thu' => ['required', 'boolean'],
            'weekSchedule.fri' => ['required', 'boolean'],
            'weekSchedule.sat' => ['required', 'boolean'],
            'weekSchedule.sun' => ['required', 'boolean'],
        ]);

        $unit->week_schedule = $validated['weekSchedule'];
        $unit->save();

        return response()->json($this->toPayload($unit->fresh()->load('property:id,property_name')));
    }

    private function toPayload(Unit $unit): array
    {
        $defaults = Unit::defaultWeekSchedule();
        $week = array_merge($defaults, is_array($unit->week_schedule) ? array_intersect_key($unit->week_schedule, $defaults) : []);

        return [
            'id' => $unit->id,
            'propertyId' => $unit->property_id,
            'propertyName' => $unit->property?->property_name,
            'name' => $unit->name,
            'details' => $unit->details,
            'description' => $unit->description,
            'images' => is_array($unit->images) ? $unit->images : [],
            'type' => $unit->type,
            'maxGuests' => $unit->max_guests,
            'bedrooms' => $unit->bedrooms,
            'beds' => $unit->beds,
            'pricePerNight' => (float) $unit->price_per_night,
            'currency' => $unit->currency,
            'status' => $unit->status,
            'weekSchedule' => $week,
        ];
    }
}
