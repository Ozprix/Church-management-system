<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreFundRequest;
use App\Http\Requests\Finance\UpdateFundRequest;
use App\Http\Resources\Finance\FundResource;
use App\Models\Fund;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FundController extends Controller
{
    public function __construct(private readonly FinanceService $financeService)
    {
        $this->middleware('feature:finance');
        $this->middleware('can:finance.manage_funds');
    }

    public function index(Request $request): JsonResponse
    {
        $funds = Fund::query()
            ->when($request->query('is_active'), fn ($query, $value) => $query->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->query('search'), function ($query, string $search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return FundResource::collection($funds)->response();
    }

    public function store(StoreFundRequest $request): JsonResponse
    {
        $fund = $this->financeService->createFund($request->validated());

        return FundResource::make($fund)->response()->setStatusCode(201);
    }

    public function show(Fund $fund): JsonResponse
    {
        return FundResource::make($fund)->response();
    }

    public function update(UpdateFundRequest $request, Fund $fund): JsonResponse
    {
        $fund = $this->financeService->updateFund($fund, $request->validated());

        return FundResource::make($fund)->response();
    }

    public function destroy(Fund $fund): JsonResponse
    {
        $fund->delete();

        return response()->json([], 204);
    }
}
