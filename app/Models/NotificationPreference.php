<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;
    public $primaryKey = 'uuid';
    protected $keyType = 'string';

    protected $fillable = [
        'user_uuid',
        'new_booking',
        'cancellation',
        'check_in',
        'check_out',
        'payment',
        'review',
    ];

    protected function casts(): array
    {
        return [
            'new_booking' => 'boolean',
            'cancellation' => 'boolean',
            'check_in' => 'boolean',
            'check_out' => 'boolean',
            'payment' => 'boolean',
            'review' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function ensureForUser(User $user): self
    {
        return self::firstOrCreate(
            ['user_uuid' => $user->uuid],
            [
                'new_booking' => true,
                'cancellation' => true,
                'check_in' => true,
                'check_out' => false,
                'payment' => true,
                'review' => false,
            ],
        );
    }
}
