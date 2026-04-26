<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPayment extends Model
{
    public const TYPE_GCASH = 'gcash';

    public const TYPE_CASH = 'cash';

    public const TYPE_BANK_TRANSFER = 'bank_transfer';

    public const TYPE_CARD = 'card';

    public const TYPE_OTHER = 'other';

    public const KIND_PAYMENT = 'payment';

    public const KIND_REFUND = 'refund';

    protected $fillable = [
        'booking_id',
        'user_id',
        'amount',
        'currency',
        'payment_type',
        'transaction_kind',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'user_id' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
