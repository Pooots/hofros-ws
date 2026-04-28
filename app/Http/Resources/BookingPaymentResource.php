<?php

namespace App\Http\Resources;

use App\Models\BookingPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'paymentType' => $this->payment_type,
            'transactionKind' => $this->transaction_kind ?? BookingPayment::KIND_PAYMENT,
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
