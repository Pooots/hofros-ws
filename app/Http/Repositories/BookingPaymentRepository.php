<?php

namespace App\Http\Repositories;

use App\Exceptions\NoBookingPaymentFoundException;
use App\Helpers\GeneralHelper;
use App\Models\Booking;
use App\Models\BookingPayment;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BookingPaymentRepository
{
    public function __construct(protected BookingPayment $payment)
    {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters): Builder
    {
        return $this->payment->newQuery()
            ->filters($filters)
            ->orderByDesc('created_at');
    }

    public function fetchOrThrow(string $key, string $value): BookingPayment
    {
        $payment = $this->payment->newQuery()->where($key, $value)->first();

        if (is_null($payment)) {
            throw new NoBookingPaymentFoundException();
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): BookingPayment
    {
        $data = GeneralHelper::unsetUnknownAndNullFields($payload, BookingPayment::DATA);

        return $this->payment->newQuery()->create($data);
    }

    /**
     * @return list<string>
     */
    public function bookingUuidsForAggregate(Booking $booking): array
    {
        $batch = $booking->portal_batch_id;
        if ($batch === null || $batch === '') {
            return [$booking->uuid];
        }

        return Booking::query()
            ->where('user_uuid', $booking->user_uuid)
            ->where('portal_batch_id', $batch)
            ->orderBy('uuid')
            ->pluck('uuid')
            ->map(static fn ($uuid): string => (string) $uuid)
            ->all();
    }

    /**
     * @param  list<string>  $bookingUuids
     * @return Collection<int, BookingPayment>
     */
    public function paymentsForBookings(array $bookingUuids): Collection
    {
        return $this->payment->newQuery()
            ->whereIn('booking_uuid', $bookingUuids)
            ->orderByDesc('created_at')
            ->get();
    }
}
