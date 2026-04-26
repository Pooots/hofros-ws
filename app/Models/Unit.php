<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unit extends Model
{
    protected $fillable = [
        'user_id',
        'property_id',
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
            'property_id' => 'integer',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function bookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function dateBlocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UnitDateBlock::class);
    }

    public function rateIntervals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UnitRateInterval::class);
    }

    public function discounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UnitDiscount::class);
    }
}
