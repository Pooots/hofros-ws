<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitRateInterval extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;
    public $primaryKey = 'uuid';
    protected $keyType = 'string';

    protected $fillable = [
        'user_uuid',
        'unit_uuid',
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

    public const DATA = [
        'user_uuid',
        'unit_uuid',
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
}
