<?php

namespace App\Http\Controllers\Api\V1\Configuration;

use App\Exceptions\TeamMemberOperationException;
use App\Http\Controllers\Controller;
use App\Http\Repositories\TeamMemberRepository;
use App\Http\Requests\TeamMember\InviteTeamMemberRequest;
use App\Http\Requests\TeamMember\ListTeamMemberRequest;
use App\Http\Requests\TeamMember\UpdateTeamMemberRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TeamMemberController extends Controller
{
    public function __construct(protected TeamMemberRepository $memberRepository)
    {
    }

    public function index(ListTeamMemberRequest $request): JsonResponse
    {
        $owner = $request->user();
        $this->memberRepository->ensureOwnerRow($owner);

        $filters = array_merge($request->validated(), [
            'owner_user_uuid' => $owner->uuid,
        ]);

        $members = $this->memberRepository->getAll($filters, $owner->email)->get();

        return response()->json([
            'team' => TeamMemberResource::collection($members)->resolve($request),
        ]);
    }

    public function update(UpdateTeamMemberRequest $request, string $uuid): JsonResponse
    {
        $member = $this->memberRepository->fetchOrThrow('uuid', $uuid, $request->user()->uuid);

        if ($member->email === $request->user()->email) {
            throw new TeamMemberOperationException('You cannot change your own role from this list.');
        }

        $this->memberRepository->update($member, ['role' => $request->validated('role')]);

        return (new TeamMemberResource($member->fresh()))->response();
    }

    public function invite(InviteTeamMemberRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $owner = $request->user();
        $email = strtolower($validated['email']);

        if ($email === strtolower($owner->email)) {
            throw new TeamMemberOperationException('That email is already the account owner.');
        }

        if ($this->memberRepository->existsByEmail($owner->uuid, $validated['email'])) {
            throw new TeamMemberOperationException('This person is already on your team.');
        }

        $localPart = strstr($email, '@', true) ?: $email;
        $name = trim($validated['name'] ?? '') ?: ucfirst(str_replace(['.', '_'], ' ', $localPart));

        $member = $this->memberRepository->create([
            'owner_user_uuid' => $owner->uuid,
            'name' => $name,
            'email' => $validated['email'],
            'role' => $validated['role'] ?? TeamMember::ROLE_STAFF,
        ]);

        return (new TeamMemberResource($member))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
