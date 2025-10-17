<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\FinanceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceAnalyticsController extends Controller
{
    public function __construct(private readonly FinanceAnalyticsService $analyticsService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorizePermission('finance.view');

        $filters = $this->analyticsService->parseFilters($request->query());
        $metrics = $this->analyticsService->buildMetrics($filters);

        return response()->json($metrics);
    }
}
