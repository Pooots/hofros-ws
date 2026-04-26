<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingPaymentController extends Controller
{
    private const PAYMENT_TYPES = [
        BookingPayment::TYPE_GCASH,
        BookingPayment::TYPE_CASH,
        BookingPayment::TYPE_BANK_TRANSFER,
        BookingPayment::TYPE_CARD,
        BookingPayment::TYPE_OTHER,
    ];

    private const TRANSACTION_KINDS = [
        BookingPayment::KIND_PAYMENT,
        BookingPayment::KIND_REFUND,
    ];

    public function index(Request $request, int $bookingId): JsonResponse
    {
        $booking = Booking::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($bookingId)
            ->firstOrFail();

        $bookingIds = $this->bookingIdsForPaymentAggregate($booking);
        $payments = BookingPayment::query()
            ->whereIn('booking_id', $bookingIds)
            ->orderByDesc('id')
            ->get();

        return response()->json($this->buildResponse($booking, $payments));
    }

    public function store(Request $request, int $bookingId): JsonResponse
    {
        $booking = Booking::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($bookingId)
            ->firstOrFail();

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paymentType' => ['required', 'string', Rule::in(self::PAYMENT_TYPES)],
            'transactionKind' => ['sometimes', 'string', Rule::in(self::TRANSACTION_KINDS)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $kind = $validated['transactionKind'] ?? BookingPayment::KIND_PAYMENT;
        $amount = round((float) $validated['amount'], 2);

        $bookingIds = $this->bookingIdsForPaymentAggregate($booking);
        $payments = BookingPayment::query()
            ->whereIn('booking_id', $bookingIds)
            ->orderByDesc('id')
            ->get();
        $summary = $this->computeSummary($booking, $payments);
        if ($kind === BookingPayment::KIND_REFUND && $amount > $summary['refundableAmount']) {
            return response()->json([
                'message' => 'Refund amount cannot exceed paid amount available for refund.',
            ], 422);
        }

        $payment = BookingPayment::create([
            'booking_id' => $booking->id,
            'user_id' => $request->user()->id,
            'amount' => $amount,
            'currency' => $booking->currency,
            'payment_type' => $validated['paymentType'],
            'transaction_kind' => $kind,
            'notes' => $validated['notes'] ?? null,
        ]);

        $payments = BookingPayment::query()
            ->whereIn('booking_id', $bookingIds)
            ->orderByDesc('id')
            ->get();

        return response()->json(array_merge(
            ['payment' => $this->paymentPayload($payment)],
            $this->buildResponse($booking, $payments)
        ), 201);
    }

    /**
     * @return list<int>
     */
    private function bookingIdsForPaymentAggregate(Booking $booking): array
    {
        $batch = $booking->portal_batch_id;
        if ($batch === null || $batch === '') {
            return [(int) $booking->id];
        }

        return Booking::query()
            ->where('user_id', $booking->user_id)
            ->where('portal_batch_id', $batch)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param \Illuminate\Support\Collection<int, BookingPayment> $payments
     * @return array<string, mixed>
     */
    private function computeSummary(Booking $booking, $payments): array
    {
        $ids = $this->bookingIdsForPaymentAggregate($booking);
        $grandTotal = round((float) Booking::query()->whereIn('id', $ids)->sum('total_price'), 2);
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

    /**
     * @param \Illuminate\Support\Collection<int, BookingPayment> $payments
     * @return array<string, mixed>
     */
    private function buildResponse(Booking $booking, $payments): array
    {
        return [
            'summary' => $this->computeSummary($booking, $payments),
            'payments' => $payments->map(fn (BookingPayment $p) => $this->paymentPayload($p))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(BookingPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'paymentType' => $payment->payment_type,
            'transactionKind' => $payment->transaction_kind ?? BookingPayment::KIND_PAYMENT,
            'notes' => $payment->notes,
            'createdAt' => $payment->created_at?->toIso8601String(),
        ];
    }
}
