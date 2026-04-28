<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromoCode extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;
    public $primaryKey = 'uuid';
    protected $keyType = 'string';

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    public const TYPES = [
        self::TYPE_PERCENTAGE,
        self::TYPE_FIXED,
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected $fillable = [
        'user_uuid',
        'code',
        'discount_type',
        'discount_value',
        'min_nights',
        'max_uses',
        'uses_count',
        'status',
    ];

    public const DATA = [
        'user_uuid',
        'code',
        'discount_type',
        'discount_value',
        'min_nights',
        'max_uses',
        'uses_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_nights' => 'integer',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
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

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['q'])) {
            $keyword = $filters['q'];
            $query->where('code', 'LIKE', "%$keyword%");
        }

        return $query;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid');
    }
}
