<?php

namespace App\Http\Repositories;

use App\Exceptions\NotFoundException;
use App\Exceptions\PortalNotPublishedException;
use App\Models\BookingPortalConnection;
use App\Models\User;
use Illuminate\Support\Str;

class PublicDirectPortalRepository
{
    /**
     * Resolve a published merchant portal by its slug.
     *
     * @return array{user: User, connection: BookingPortalConnection, slug: string}
     */
    public function resolveBySlugOrThrow(string $slug): array
    {
        $normalized = Str::lower(trim($slug));

        $user = User::query()
            ->whereNotNull('merchant_name')
            ->get()
            ->first(function (User $u) use ($normalized): bool {
                $candidate = Str::slug((string) $u->merchant_name) ?: 'merchant';

                return $candidate === $normalized;
            });

        if ($user === null) {
            throw new NotFoundException();
        }

        $row = BookingPortalConnection::query()
            ->where('user_uuid', $user->uuid)
            ->where('portal_key', BookingPortalRepository::DIRECT_WEBSITE_KEY)
            ->first();

        if ($row === null || ! $row->guest_portal_live) {
            throw new PortalNotPublishedException();
        }

        return [
            'user' => $user,
            'connection' => $row,
            'slug' => $normalized,
        ];
    }
}
