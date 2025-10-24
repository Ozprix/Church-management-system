<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Finance\LedgerReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinanceTrialBalanceController extends Controller
{
    public function __construct(private readonly LedgerReconciliationService $reconciliationService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorizePermission('finance.view');

        $tenant = $request->attributes->get('tenant');

        $monthParam = $request->query('month');
        $month = $monthParam
            ? Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth()
            : Carbon::now()->startOfMonth();

        $trialBalance = $this->reconciliationService->buildMonthlyTrialBalance($tenant->id, $month);

        return response()->json($trialBalance);
    }
}
