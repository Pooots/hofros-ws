<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;
    public $primaryKey = 'uuid';
    protected $keyType = 'string';

    protected $fillable = [
        'user_uuid',
        'property_name',
        'contact_email',
        'phone',
        'address',
        'currency',
        'check_in_time',
        'check_out_time',
    ];

    public const DATA = [
        'user_uuid',
        'property_name',
        'contact_email',
        'phone',
        'address',
        'currency',
        'check_in_time',
        'check_out_time',
    ];

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public function scopeFilters(Builder $query, ?array $filters): Builder
    {
        $filters = $filters ?? [];

        if (! empty($filters['user_uuid'])) {
            $query->where('user_uuid', $filters['user_uuid']);
        }

        if (! empty($filters['q'])) {
            $keyword = $filters['q'];
            $query->where(function (Builder $w) use ($keyword): void {
                $w->where('property_name', 'LIKE', "%$keyword%")
                    ->orWhere('contact_email', 'LIKE', "%$keyword%")
                    ->orWhere('address', 'LIKE', "%$keyword%");
            });
        }

        return $query;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}
