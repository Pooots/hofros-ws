<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(SystemHealthService $healthService): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'meta' => $healthService->summary(),
        ]);
    }
}
