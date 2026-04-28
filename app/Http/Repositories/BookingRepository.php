<?php

namespace App\Http\Repositories;

use App\Exceptions\NoBookingFoundException;
use App\Helpers\GeneralHelper;
use App\Models\Booking;
use App\Models\Unit;
use App\Support\BookingStayConflict;
use Illuminate\Contracts\Database\Eloquent\Builder as ContractsBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BookingRepository
{
    public function __construct(protected Booking $booking)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function unitEagerLoad(): array
    {
        return [
            'unit' => static function ($q): void {
                $q->select(
                    'uuid',
                    'property_uuid',
                    'name',
                    'type',
                    'max_guests',
                    'bedrooms',
                    'beds',
                    'price_per_night',
                    'currency',
                    'status'
                )->with([
                    'property' => static function ($q2): void {
                        $q2->select('uuid', 'property_name');
                    },
                ]);
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters): Builder
    {
        return $this->booking->newQuery()
            ->with($this->unitEagerLoad())
            ->filters($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('uuid');
    }

    public function fetchOrThrow(string $key, string $value, ?string $userUuid = null, bool $withPayments = false): Booking
    {
        $query = $this->booking->newQuery()
            ->where($key, $value)
            ->with($this->unitEagerLoad());

        if ($userUuid !== null) {
            $query->where('user_uuid', $userUuid);
        }

        if ($withPayments) {
            $query->withSum('payments', 'amount');
        }

        $booking = $query->first();
        if (is_null($booking)) {
            throw new NoBookingFoundException();
        }

        return $booking;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): Booking
    {
        $data = GeneralHelper::unsetUnknownAndNullFields($payload, Booking::DATA);

        return $this->booking->newQuery()->create($data);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Booking $booking, array $payload): bool
    {
        $data = GeneralHelper::unsetUnknownFields($payload, Booking::DATA);

        return $booking->update($data);
    }

    public function delete(Booking $booking): void
    {
        $booking->delete();
    }

    /**
     * Search predicate covering guest fields, references, and unit names — including portal batch siblings.
     */
    public function applySearch(Builder $query, string $userUuid, string $keyword): void
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $keyword).'%';

        $query->where(function ($w) use ($like, $userUuid): void {
            $w->where('guest_name', 'like', $like)
                ->orWhere('guest_email', 'like', $like)
                ->orWhere('guest_phone', 'like', $like)
                ->orWhere('reference', 'like', $like)
                ->orWhereHas('unit', function ($u) use ($like): void {
                    $u->where('name', 'like', $like);
                })
                ->orWhere(function (Builder $w2) use ($like, $userUuid): void {
                    $w2->whereNotNull('bookings.portal_batch_id')
                        ->whereExists(function (QueryBuilder $sub) use ($like, $userUuid): void {
                            $sub->from('bookings as batch_sibling')
                                ->whereColumn('batch_sibling.portal_batch_id', 'bookings.portal_batch_id')
                                ->where('batch_sibling.user_uuid', '=', $userUuid)
                                ->where(function (QueryBuilder $w3) use ($like): void {
                                    $w3->where('batch_sibling.guest_name', 'like', $like)
                                        ->orWhere('batch_sibling.guest_email', 'like', $like)
                                        ->orWhere('batch_sibling.guest_phone', 'like', $like)
                                        ->orWhere('batch_sibling.reference', 'like', $like)
                                        ->orWhereExists(function (QueryBuilder $sub2) use ($like): void {
                                            $sub2->selectRaw('1')
                                                ->from('units')
                                                ->whereColumn('units.uuid', 'batch_sibling.unit_uuid')
                                                ->where('units.name', 'like', $like);
                                        });
                                });
                        });
                });
        });
    }

    /**
     * Hide sibling rows in a multi-unit portal batch on the list view.
     */
    public function applyPortalBatchListCollapseScope(Builder $query, string $userUuid): void
    {
        $query->where(function (Builder $w) use ($userUuid): void {
            $w->whereNull('portal_batch_id')
                ->orWhereIn(
                    'uuid',
                    Booking::query()
                        ->select('uuid')
                        ->where('user_uuid', $userUuid)
                        ->whereNotNull('portal_batch_id')
                        ->groupBy('portal_batch_id'),
                );
        });
    }

    /**
     * @return Collection<int, Booking>
     */
    public function batchSiblings(Booking $booking): Collection
    {
        $batchId = (string) $booking->portal_batch_id;

        return Booking::query()
            ->where('user_uuid', $booking->user_uuid)
            ->where('portal_batch_id', $batchId)
            ->with($this->unitEagerLoad())
            ->orderBy('uuid')
            ->get();
    }

    public function uniqueReference(): string
    {
        for ($i = 0; $i < 12; $i++) {
            $candidate = 'HFR-'.Str::upper(Str::random(8));
            if (! Booking::query()->where('reference', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'HFR-'.Str::upper(Str::uuid()->toString());
    }

    /**
     * @return Collection<int, Unit>
     */
    public function getAvailableMatchingUnits(
        string $userUuid,
        Unit $template,
        Carbon $checkIn,
        Carbon $checkOut,
        ?string $exceptBookingUuid
    ): Collection {
        $candidates = Unit::query()
            ->where('user_uuid', $userUuid)
            ->where('status', 'active')
            ->where('property_uuid', $template->property_uuid)
            ->where('max_guests', $template->max_guests)
            ->where('bedrooms', $template->bedrooms)
            ->where('beds', $template->beds)
            ->where(static function (Builder $q) use ($template): void {
                if ($template->type === null) {
                    $q->whereNull('type');
                } else {
                    $q->where('type', $template->type);
                }
            })
            ->with([
                'property' => static function ($q): void {
                    $q->select('uuid', 'property_name');
                },
            ])
            ->orderBy('uuid')
            ->get(['uuid', 'name', 'property_uuid']);

        return $candidates->filter(function (Unit $candidate) use ($userUuid, $checkIn, $checkOut, $exceptBookingUuid): bool {
            $unitUuid = $candidate->uuid;
            if (BookingStayConflict::hasOverlappingBooking($userUuid, $unitUuid, $checkIn, $checkOut, $exceptBookingUuid)) {
                return false;
            }
            if (BookingStayConflict::hasOverlappingBlock($userUuid, $unitUuid, $checkIn, $checkOut)) {
                return false;
            }

            return true;
        })->values();
    }

    public function hasAvailableMatchingUnit(
        string $userUuid,
        Unit $template,
        Carbon $checkIn,
        Carbon $checkOut,
        ?string $exceptBookingUuid
    ): bool {
        return $this->getAvailableMatchingUnits($userUuid, $template, $checkIn, $checkOut, $exceptBookingUuid)->isNotEmpty();
    }
}
