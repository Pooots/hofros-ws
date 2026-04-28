<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;
    public $primaryKey = 'uuid';
    protected $keyType = 'string';

    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_STAFF = 'staff';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_MANAGER,
        self::ROLE_STAFF,
    ];

    protected $fillable = [
        'owner_user_uuid',
        'name',
        'email',
        'role',
    ];

    public const DATA = [
        'owner_user_uuid',
        'name',
        'email',
        'role',
    ];

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public function scopeFilters(Builder $query, ?array $filters): Builder
    {
        $filters = $filters ?? [];

        if (! empty($filters['owner_user_uuid'])) {
            $query->where('owner_user_uuid', $filters['owner_user_uuid']);
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['q'])) {
            $keyword = $filters['q'];
            $query->where(function (Builder $w) use ($keyword): void {
                $w->where('name', 'LIKE', "%$keyword%")
                    ->orWhere('email', 'LIKE', "%$keyword%");
            });
        }

        return $query;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_uuid');
    }

    public static function ensureOwnerRow(User $owner): self
    {
        return self::firstOrCreate(
            [
                'owner_user_uuid' => $owner->uuid,
                'email' => $owner->email,
            ],
            [
                'name' => trim(implode(' ', array_filter([
                    $owner->first_name,
                    $owner->middle_name,
                    $owner->last_name,
                ]))),
                'role' => self::ROLE_ADMIN,
            ],
        );
    }
}
