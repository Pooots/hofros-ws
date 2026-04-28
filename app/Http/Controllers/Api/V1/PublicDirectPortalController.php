<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Repositories\PublicDirectPortalRepository;
use App\Http\Resources\PublicDirectPortalResource;
use Illuminate\Http\JsonResponse;

class PublicDirectPortalController extends Controller
{
    public function __construct(protected PublicDirectPortalRepository $portalRepository)
    {
    }

    public function show(string $slug): JsonResponse
    {
        $resolved = $this->portalRepository->resolveBySlugOrThrow($slug);

        return (new PublicDirectPortalResource($resolved))->response();
    }
}
