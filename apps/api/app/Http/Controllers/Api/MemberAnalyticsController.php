<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\MemberAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberAnalyticsController extends Controller
{
    public function __construct(private readonly MemberAnalyticsService $analyticsService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorizePermission('members.view');

        $filters = $this->analyticsService->parseFilters($request->query());
        $metrics = $this->analyticsService->buildMetrics($filters);

        return response()->json($metrics);
    }
}
