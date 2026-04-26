<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitRateInterval extends Model
{
    protected $fillable = [
        'user_id',
        'unit_id',
        'name',
        'start_date',
        'end_date',
        'min_los',
        'max_los',
        'closed_to_arrival',
        'closed_to_departure',
        'days_of_week',
        'day_prices',
        'base_price',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'min_los' => 'integer',
            'max_los' => 'integer',
            'closed_to_arrival' => 'boolean',
            'closed_to_departure' => 'boolean',
            'days_of_week' => 'array',
            'day_prices' => 'array',
            'base_price' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
