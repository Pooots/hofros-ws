<?php

namespace App\Http\Controllers\Api\v1\Configuration;

use App\Http\Controllers\Controller;
use App\Http\Repositories\NotificationPreferenceRepository;
use App\Http\Requests\NotificationPreference\UpdateNotificationPreferenceRequest;
use App\Http\Resources\NotificationPreferenceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function __construct(protected NotificationPreferenceRepository $preferenceRepository)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $row = $this->preferenceRepository->ensureForUser($request->user());

        return (new NotificationPreferenceResource($row->fresh()))->response();
    }

    public function update(UpdateNotificationPreferenceRequest $request): JsonResponse
    {
        $row = $this->preferenceRepository->ensureForUser($request->user());
        $this->preferenceRepository->update($row, $request->toModelPayload());

        return (new NotificationPreferenceResource($row->fresh()))->response();
    }
}
