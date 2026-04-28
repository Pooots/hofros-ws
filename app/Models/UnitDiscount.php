<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitDiscount extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;
    public $primaryKey = 'uuid';
    protected $keyType = 'string';

    public const TYPE_EARLY_BIRD = 'early_bird';
    public const TYPE_LONG_STAY = 'long_stay';
    public const TYPE_LAST_MINUTE = 'last_minute';
    public const TYPE_WEEKEND_DISCOUNT = 'weekend_discount';
    public const TYPE_DATE_RANGE = 'date_range';

    public const TYPES = [
        self::TYPE_EARLY_BIRD,
        self::TYPE_LONG_STAY,
        self::TYPE_LAST_MINUTE,
        self::TYPE_WEEKEND_DISCOUNT,
        self::TYPE_DATE_RANGE,
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected $fillable = [
        'user_uuid',
        'unit_uuid',
        'discount_type',
        'discount_percent',
        'min_days_in_advance',
        'min_nights',
        'valid_from',
        'valid_to',
        'status',
    ];

    public const DATA = [
        'user_uuid',
        'unit_uuid',
        'discount_type',
        'discount_percent',
        'min_days_in_advance',
        'min_nights',
        'valid_from',
        'valid_to',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'min_days_in_advance' => 'integer',
            'min_nights' => 'integer',
            'valid_from' => 'date',
            'valid_to' => 'date',
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

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['discount_type'])) {
            $query->where('discount_type', $filters['discount_type']);
        }

        return $query;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_uuid');
    }
}
