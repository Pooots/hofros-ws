<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
{
    protected $fillable = [
        'owner_user_id',
        'name',
        'email',
        'role',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public static function ensureOwnerRow(User $owner): self
    {
        return self::firstOrCreate(
            [
                'owner_user_id' => $owner->id,
                'email' => $owner->email,
            ],
            [
                'name' => $owner->name,
                'role' => 'admin',
            ],
        );
    }
}
