<?php

namespace App\Http\Controllers\Api\V1\Configuration;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $row = NotificationPreference::ensureForUser($request->user());

        return response()->json($this->toPayload($row));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'newBooking' => ['required', 'boolean'],
            'cancellation' => ['required', 'boolean'],
            'checkIn' => ['required', 'boolean'],
            'checkOut' => ['required', 'boolean'],
            'payment' => ['required', 'boolean'],
            'review' => ['required', 'boolean'],
        ]);

        $row = NotificationPreference::ensureForUser($request->user());

        $row->update([
            'new_booking' => $validated['newBooking'],
            'cancellation' => $validated['cancellation'],
            'check_in' => $validated['checkIn'],
            'check_out' => $validated['checkOut'],
            'payment' => $validated['payment'],
            'review' => $validated['review'],
        ]);

        return response()->json($this->toPayload($row->fresh()));
    }

    private function toPayload(NotificationPreference $row): array
    {
        return [
            'newBooking' => $row->new_booking,
            'cancellation' => $row->cancellation,
            'checkIn' => $row->check_in,
            'checkOut' => $row->check_out,
            'payment' => $row->payment,
            'review' => $row->review,
        ];
    }
}
