<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCode extends Model
{
    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'user_id',
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
            'user_id' => 'integer',
            'discount_value' => 'decimal:2',
            'min_nights' => 'integer',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
