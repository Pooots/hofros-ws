<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitDateBlock extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;
    public $primaryKey = 'uuid';
    protected $keyType = 'string';

    protected $fillable = [
        'user_uuid',
        'unit_uuid',
        'start_date',
        'end_date',
        'label',
        'notes',
    ];

    public const DATA = [
        'user_uuid',
        'unit_uuid',
        'start_date',
        'end_date',
        'label',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
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

        if (! empty($filters['from'])) {
            $query->where('end_date', '>', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('start_date', '<', $filters['to']);
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
