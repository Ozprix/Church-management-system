<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AttendanceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceAnalyticsController extends Controller
{
    public function __construct(private readonly AttendanceAnalyticsService $analytics)
    {
        $this->middleware('feature:attendance');
        $this->middleware('can:attendance.view');
    }

    public function __invoke(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant?->id ?? $request->user()?->tenant_id;

        if (! $tenantId) {
            return response()->json([
                'message' => __('Unable to resolve tenant for analytics.'),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $filters = $this->analytics->parseFilters($request->query());
        $payload = $this->analytics->metrics((int) $tenantId, $filters);

        return response()->json([
            'data' => $payload,
            'meta' => [
                'filters' => [
                    'from' => $filters['from']->toDateString(),
                    'to' => $filters['to']->toDateString(),
                    'service_id' => $filters['service_id'],
                    'status' => $filters['status'],
                ],
            ],
        ]);
    }
}
