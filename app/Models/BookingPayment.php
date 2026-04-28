<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPayment extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;
    public $primaryKey = 'uuid';
    protected $keyType = 'string';

    public const TYPE_GCASH = 'gcash';
    public const TYPE_CASH = 'cash';
    public const TYPE_BANK_TRANSFER = 'bank_transfer';
    public const TYPE_CARD = 'card';
    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_GCASH,
        self::TYPE_CASH,
        self::TYPE_BANK_TRANSFER,
        self::TYPE_CARD,
        self::TYPE_OTHER,
    ];

    public const KIND_PAYMENT = 'payment';
    public const KIND_REFUND = 'refund';

    public const KINDS = [
        self::KIND_PAYMENT,
        self::KIND_REFUND,
    ];

    protected $fillable = [
        'booking_uuid',
        'user_uuid',
        'amount',
        'currency',
        'payment_type',
        'transaction_kind',
        'notes',
    ];

    public const DATA = [
        'booking_uuid',
        'user_uuid',
        'amount',
        'currency',
        'payment_type',
        'transaction_kind',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'booking_uuid' => 'string',
            'user_uuid' => 'string',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public function scopeFilters(Builder $query, ?array $filters): Builder
    {
        $filters = $filters ?? [];

        if (! empty($filters['booking_uuids']) && is_array($filters['booking_uuids'])) {
            $query->whereIn('booking_uuid', $filters['booking_uuids']);
        }

        if (! empty($filters['booking_uuid'])) {
            $query->where('booking_uuid', $filters['booking_uuid']);
        }

        if (! empty($filters['transaction_kind'])) {
            $query->where('transaction_kind', $filters['transaction_kind']);
        }

        return $query;
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_uuid');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid');
    }
}
