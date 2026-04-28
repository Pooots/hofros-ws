<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;
    public $primaryKey = 'uuid';
    protected $keyType = 'string';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected $fillable = [
        'user_uuid',
        'property_uuid',
        'name',
        'details',
        'description',
        'images',
        'type',
        'max_guests',
        'bedrooms',
        'beds',
        'price_per_night',
        'currency',
        'status',
        'week_schedule',
    ];

    public const DATA = [
        'user_uuid',
        'property_uuid',
        'name',
        'details',
        'description',
        'images',
        'type',
        'max_guests',
        'bedrooms',
        'beds',
        'price_per_night',
        'currency',
        'status',
        'week_schedule',
    ];

    protected function casts(): array
    {
        return [
            'max_guests' => 'integer',
            'bedrooms' => 'integer',
            'beds' => 'integer',
            'price_per_night' => 'decimal:2',
            'images' => 'array',
            'week_schedule' => 'array',
        ];
    }

    /** @return array<string, bool> */
    public static function defaultWeekSchedule(): array
    {
        return [
            'mon' => true,
            'tue' => true,
            'wed' => true,
            'thu' => true,
            'fri' => true,
            'sat' => true,
            'sun' => true,
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

        if (! empty($filters['property_uuid'])) {
            $query->where('property_uuid', $filters['property_uuid']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['q'])) {
            $keyword = $filters['q'];
            $query->where(function (Builder $w) use ($keyword): void {
                $w->where('name', 'LIKE', "%$keyword%")
                    ->orWhere('description', 'LIKE', "%$keyword%")
                    ->orWhere('details', 'LIKE', "%$keyword%");
            });
        }

        return $query;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function dateBlocks(): HasMany
    {
        return $this->hasMany(UnitDateBlock::class);
    }

    public function rateIntervals(): HasMany
    {
        return $this->hasMany(UnitRateInterval::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(UnitDiscount::class);
    }
}
