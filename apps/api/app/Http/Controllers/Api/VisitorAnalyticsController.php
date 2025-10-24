<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\VisitorAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorAnalyticsController extends Controller
{
    public function __construct(private readonly VisitorAnalyticsService $analyticsService)
    {
        $this->middleware('feature:visitors');
        $this->middleware('can:visitors.manage_followups');
    }

    public function __invoke(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant?->id ?? $request->user()?->tenant_id;

        if (! $tenantId) {
            return response()->json([
                'message' => 'Unable to resolve tenant context for analytics.',
            ], 422);
        }

        $payload = $this->analyticsService->metrics((int) $tenantId);

        return response()->json(['data' => $payload]);
    }
}
