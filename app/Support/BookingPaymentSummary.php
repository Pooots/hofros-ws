<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingPayment;
use Illuminate\Support\Collection;

class BookingPaymentSummary
{
    /**
     * @param  list<string>  $bookingUuids
     * @param  Collection<int, BookingPayment>  $payments
     * @return array<string, mixed>
     */
    public static function compute(Booking $booking, array $bookingUuids, Collection $payments): array
    {
        $grandTotal = round((float) Booking::query()->whereIn('uuid', $bookingUuids)->sum('total_price'), 2);
        $totalPaid = round((float) $payments
            ->where('transaction_kind', BookingPayment::KIND_PAYMENT)
            ->sum('amount'), 2);
        $totalRefunded = round((float) $payments
            ->where('transaction_kind', BookingPayment::KIND_REFUND)
            ->sum('amount'), 2);
        $netPaid = round(max($totalPaid - $totalRefunded, 0), 2);
        $balanceDue = round(max($grandTotal - $netPaid, 0), 2);
        $overpaid = round(max($netPaid - $grandTotal, 0), 2);
        $refundableAmount = $netPaid;

        return [
            'grandTotal' => $grandTotal,
            'subtotal' => $grandTotal,
            'totalPaid' => $totalPaid,
            'totalRefunded' => $totalRefunded,
            'netPaid' => $netPaid,
            'balanceDue' => $balanceDue,
            'overpaid' => $overpaid,
            'refundableAmount' => $refundableAmount,
            'currency' => $booking->currency,
        ];
    }
}
