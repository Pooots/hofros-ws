<?php

namespace App\Http\Repositories;

use App\Helpers\GeneralHelper;
use App\Models\User;

class UserRepository
{
    public const DATA = [
        'merchant_name',
        'first_name',
        'middle_name',
        'last_name',
        'contact_number',
        'address',
        'email',
        'password',
    ];

    public function __construct(protected User $user)
    {
    }

    public function findByEmail(string $email): ?User
    {
        return $this->user->newQuery()->where('email', $email)->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): User
    {
        $data = GeneralHelper::unsetUnknownAndNullFields($payload, self::DATA);

        return $this->user->newQuery()->create($data);
    }
}
