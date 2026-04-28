<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;
    public $primaryKey = 'uuid';
    protected $keyType = 'string';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_ASSIGNED,
        self::STATUS_CHECKED_IN,
        self::STATUS_CHECKED_OUT,
        self::STATUS_CANCELLED,
    ];

    public const SOURCE_DIRECT_PORTAL = 'direct_portal';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'user_uuid',
        'unit_uuid',
        'reference',
        'guest_name',
        'guest_email',
        'guest_phone',
        'check_in',
        'check_out',
        'adults',
        'children',
        'total_price',
        'currency',
        'source',
        'status',
        'notes',
        'portal_batch_id',
    ];

    public const DATA = [
        'user_uuid',
        'unit_uuid',
        'reference',
        'guest_name',
        'guest_email',
        'guest_phone',
        'check_in',
        'check_out',
        'adults',
        'children',
        'total_price',
        'currency',
        'source',
        'status',
        'notes',
        'portal_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'adults' => 'integer',
            'children' => 'integer',
            'total_price' => 'decimal:2',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public function scopeFilters(Builder $query, ?array $filters): Builder
    {
        $filters = $filters ?? [];

        if (! empty($filters['user_uuid'])) {
            $query->where('user_uuid', $filters['user_uuid']);
        }

        if (! empty($filters['unit_uuid'])) {
            $query->where('unit_uuid', $filters['unit_uuid']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['portal_batch_id'])) {
            $query->where('portal_batch_id', $filters['portal_batch_id']);
        }

        return $query;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class)->orderByDesc('created_at');
    }
}
