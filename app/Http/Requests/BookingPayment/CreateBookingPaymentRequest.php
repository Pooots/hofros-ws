<?php

namespace App\Http\Requests\BookingPayment;

use App\Models\BookingPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBookingPaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paymentType' => ['required', 'string', Rule::in(BookingPayment::TYPES)],
            'transactionKind' => ['sometimes', 'string', Rule::in(BookingPayment::KINDS)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
