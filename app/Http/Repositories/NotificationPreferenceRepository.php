<?php

namespace App\Http\Repositories;

use App\Helpers\GeneralHelper;
use App\Models\NotificationPreference;
use App\Models\User;

class NotificationPreferenceRepository
{
    public const DATA = [
        'new_booking',
        'cancellation',
        'check_in',
        'check_out',
        'payment',
        'review',
    ];

    public function __construct(protected NotificationPreference $preference)
    {
    }

    public function ensureForUser(User $user): NotificationPreference
    {
        return NotificationPreference::ensureForUser($user);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(NotificationPreference $preference, array $payload): bool
    {
        $data = GeneralHelper::unsetUnknownFields($payload, self::DATA);

        return $preference->update($data);
    }
}
