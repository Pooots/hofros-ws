<?php

namespace App\Http\Requests\NotificationPreference;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'newBooking' => ['required', 'boolean'],
            'cancellation' => ['required', 'boolean'],
            'checkIn' => ['required', 'boolean'],
            'checkOut' => ['required', 'boolean'],
            'payment' => ['required', 'boolean'],
            'review' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toModelPayload(): array
    {
        $validated = $this->validated();

        return [
            'new_booking' => $validated['newBooking'],
            'cancellation' => $validated['cancellation'],
            'check_in' => $validated['checkIn'],
            'check_out' => $validated['checkOut'],
            'payment' => $validated['payment'],
            'review' => $validated['review'],
        ];
    }
}
