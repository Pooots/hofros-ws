<?php

namespace App\Http\Controllers\Api\v1;

use App\Exceptions\BookingValidationException;
use App\Http\Controllers\Controller;
use App\Http\Repositories\BookingPaymentRepository;
use App\Http\Repositories\BookingRepository;
use App\Http\Requests\BookingPayment\CreateBookingPaymentRequest;
use App\Http\Resources\BookingPaymentResource;
use App\Models\BookingPayment;
use App\Support\BookingPaymentSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BookingPaymentController extends Controller
{
    public function __construct(
        protected BookingPaymentRepository $paymentRepository,
        protected BookingRepository $bookingRepository,
    ) {
    }

    public function index(Request $request, string $bookingUuid): JsonResponse
    {
        $booking = $this->bookingRepository->fetchOrThrow('uuid', $bookingUuid, $request->user()->uuid);

        $bookingUuids = $this->paymentRepository->bookingUuidsForAggregate($booking);
        $payments = $this->paymentRepository->paymentsForBookings($bookingUuids);

        return response()->json([
            'summary' => BookingPaymentSummary::compute($booking, $bookingUuids, $payments),
            'payments' => BookingPaymentResource::collection($payments)->resolve($request),
        ]);
    }

    public function store(CreateBookingPaymentRequest $request, string $bookingUuid): JsonResponse
    {
        $booking = $this->bookingRepository->fetchOrThrow('uuid', $bookingUuid, $request->user()->uuid);

        $validated = $request->validated();

        $kind = $validated['transactionKind'] ?? BookingPayment::KIND_PAYMENT;
        $amount = round((float) $validated['amount'], 2);

        $bookingUuids = $this->paymentRepository->bookingUuidsForAggregate($booking);
        $existingPayments = $this->paymentRepository->paymentsForBookings($bookingUuids);
        $summary = BookingPaymentSummary::compute($booking, $bookingUuids, $existingPayments);

        if ($kind === BookingPayment::KIND_REFUND && $amount > $summary['refundableAmount']) {
            throw new BookingValidationException('Refund amount cannot exceed paid amount available for refund.');
        }

        $payment = $this->paymentRepository->create([
            'booking_uuid' => $booking->uuid,
            'user_uuid' => $request->user()->uuid,
            'amount' => $amount,
            'currency' => $booking->currency,
            'payment_type' => $validated['paymentType'],
            'transaction_kind' => $kind,
            'notes' => $validated['notes'] ?? null,
        ]);

        $payments = $this->paymentRepository->paymentsForBookings($bookingUuids);

        return response()->json([
            'payment' => (new BookingPaymentResource($payment))->resolve($request),
            'summary' => BookingPaymentSummary::compute($booking, $bookingUuids, $payments),
            'payments' => BookingPaymentResource::collection($payments)->resolve($request),
        ], Response::HTTP_CREATED);
    }
}
