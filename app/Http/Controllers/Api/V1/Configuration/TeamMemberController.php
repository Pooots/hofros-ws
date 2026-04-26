<?php

namespace App\Http\Controllers\Api\V1\Configuration;

use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamMemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $owner = $request->user();
        TeamMember::ensureOwnerRow($owner);

        $members = TeamMember::query()
            ->where('owner_user_id', $owner->id)
            ->orderByRaw("CASE WHEN email = ? THEN 0 ELSE 1 END", [$owner->email])
            ->orderBy('name')
            ->get()
            ->map(fn (TeamMember $member) => $this->toPayload($member));

        return response()->json(['team' => $members]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $member = TeamMember::query()
            ->where('owner_user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        if ($member->email === $request->user()->email) {
            return response()->json([
                'message' => 'You cannot change your own role from this list.',
            ], 422);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(['admin', 'manager', 'staff'])],
        ]);

        $member->update(['role' => $validated['role']]);

        return response()->json($this->toPayload($member->fresh()));
    }

    public function invite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', Rule::in(['admin', 'manager', 'staff'])],
        ]);

        $owner = $request->user();
        $email = strtolower($validated['email']);

        if ($email === strtolower($owner->email)) {
            return response()->json([
                'message' => 'That email is already the account owner.',
            ], 422);
        }

        $exists = TeamMember::query()
            ->where('owner_user_id', $owner->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This person is already on your team.',
            ], 422);
        }

        $localPart = strstr($email, '@', true) ?: $email;
        $name = trim($validated['name'] ?? '') ?: ucfirst(str_replace(['.', '_'], ' ', $localPart));

        $member = TeamMember::create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'email' => $validated['email'],
            'role' => $validated['role'] ?? 'staff',
        ]);

        return response()->json($this->toPayload($member), 201);
    }

    private function toPayload(TeamMember $member): array
    {
        $initials = collect(preg_split('/\s+/', trim($member->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        if ($initials === '') {
            $initials = strtoupper(mb_substr($member->email, 0, 2));
        }

        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->role,
            'initials' => $initials,
        ];
    }
}
