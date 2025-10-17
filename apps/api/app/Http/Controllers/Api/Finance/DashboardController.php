<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\DonationResource;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DashboardController extends Controller
{
    public function __construct(private readonly FinanceService $financeService)
    {
        $this->middleware('feature:finance');
        $this->middleware('can:finance.view_dashboard');
    }

    public function __invoke(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant) {
            throw new BadRequestHttpException('Tenant context is required.');
        }

        $summary = $this->financeService->getDashboardSummary($tenant->id);

        return response()->json([
            'totals' => $summary['totals'],
            'recurring_pledges' => $summary['recurring_pledges'],
            'top_funds' => $summary['top_funds'],
            'recent_donations' => DonationResource::collection($summary['recent_donations'])->resolve(),
        ]);
    }
}
