<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitDiscount extends Model
{
    public const TYPE_EARLY_BIRD = 'early_bird';

    public const TYPE_LONG_STAY = 'long_stay';

    public const TYPE_LAST_MINUTE = 'last_minute';

    public const TYPE_WEEKEND_DISCOUNT = 'weekend_discount';

    public const TYPE_DATE_RANGE = 'date_range';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'user_id',
        'unit_id',
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
            'user_id' => 'integer',
            'unit_id' => 'integer',
            'discount_percent' => 'decimal:2',
            'min_days_in_advance' => 'integer',
            'min_nights' => 'integer',
            'valid_from' => 'date',
            'valid_to' => 'date',
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
