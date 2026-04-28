<?php

namespace App\Http\Repositories;

use App\Exceptions\NoTeamMemberFoundException;
use App\Helpers\GeneralHelper;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;

class TeamMemberRepository
{
    public function __construct(protected TeamMember $member)
    {
    }

    public function ensureOwnerRow(User $owner): TeamMember
    {
        return TeamMember::ensureOwnerRow($owner);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters, ?string $ownerEmail = null): Builder
    {
        $query = $this->member->newQuery()->filters($filters);

        if ($ownerEmail !== null) {
            $query->orderByRaw('CASE WHEN email = ? THEN 0 ELSE 1 END', [$ownerEmail]);
        }

        return $query->orderBy('name');
    }

    public function fetchOrThrow(string $key, string $value, ?string $ownerUuid = null): TeamMember
    {
        $query = $this->member->newQuery()->where($key, $value);
        if ($ownerUuid !== null) {
            $query->where('owner_user_uuid', $ownerUuid);
        }
        $member = $query->first();

        if (is_null($member)) {
            throw new NoTeamMemberFoundException();
        }

        return $member;
    }

    public function existsByEmail(string $ownerUuid, string $email): bool
    {
        return $this->member->newQuery()
            ->where('owner_user_uuid', $ownerUuid)
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): TeamMember
    {
        $data = GeneralHelper::unsetUnknownAndNullFields($payload, TeamMember::DATA);

        return $this->member->newQuery()->create($data);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(TeamMember $member, array $payload): bool
    {
        $data = GeneralHelper::unsetUnknownFields($payload, TeamMember::DATA);

        return $member->update($data);
    }
}
