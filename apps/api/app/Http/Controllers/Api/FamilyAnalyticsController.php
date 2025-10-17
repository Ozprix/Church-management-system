<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\FamilyAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyAnalyticsController extends Controller
{
    public function __construct(private readonly FamilyAnalyticsService $analyticsService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorizePermission('families.view');

        $filters = $this->analyticsService->parseFilters($request->query());
        $metrics = $this->analyticsService->buildMetrics($filters);

        return response()->json($metrics);
    }
}
