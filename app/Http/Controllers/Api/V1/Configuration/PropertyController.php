<?php

namespace App\Http\Controllers\Api\V1\Configuration;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $properties = Property::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Property $property) => $this->toPayload($property));

        return response()->json(['properties' => $properties]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'propertyName' => ['required', 'string', 'max:255'],
            'contactEmail' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:2000'],
            'currency' => ['required', 'string', 'max:16'],
            'checkInTime' => ['required', 'string', 'max:8'],
            'checkOutTime' => ['required', 'string', 'max:8'],
        ]);

        $property = Property::create([
            'user_id' => $request->user()->id,
            'property_name' => $validated['propertyName'],
            'contact_email' => $validated['contactEmail'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'currency' => $validated['currency'],
            'check_in_time' => $validated['checkInTime'],
            'check_out_time' => $validated['checkOutTime'],
        ]);

        return response()->json($this->toPayload($property), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $property = Property::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $validated = $request->validate([
            'propertyName' => ['required', 'string', 'max:255'],
            'contactEmail' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:2000'],
            'currency' => ['required', 'string', 'max:16'],
            'checkInTime' => ['required', 'string', 'max:8'],
            'checkOutTime' => ['required', 'string', 'max:8'],
        ]);

        $property->update([
            'property_name' => $validated['propertyName'],
            'contact_email' => $validated['contactEmail'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'currency' => $validated['currency'],
            'check_in_time' => $validated['checkInTime'],
            'check_out_time' => $validated['checkOutTime'],
        ]);

        return response()->json($this->toPayload($property->fresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $property = Property::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $property->delete();

        return response()->json(['message' => 'Property deleted.']);
    }

    private function toPayload(Property $property): array
    {
        return [
            'id' => $property->id,
            'propertyName' => $property->property_name,
            'contactEmail' => $property->contact_email,
            'phone' => $property->phone,
            'address' => $property->address,
            'currency' => $property->currency,
            'checkInTime' => $property->check_in_time,
            'checkOutTime' => $property->check_out_time,
        ];
    }
}
