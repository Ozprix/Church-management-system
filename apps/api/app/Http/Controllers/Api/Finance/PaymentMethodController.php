<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePaymentMethodRequest;
use App\Http\Requests\Finance\UpdatePaymentMethodRequest;
use App\Http\Resources\Finance\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function __construct(private readonly FinanceService $financeService)
    {
        $this->middleware('feature:finance');
        $this->middleware('can:finance.manage_payment_methods');
    }

    public function index(Request $request): JsonResponse
    {
        $methods = PaymentMethod::query()
            ->with('member')
            ->when($request->query('member_id'), fn ($query, $memberId) => $query->where('member_id', $memberId))
            ->when($request->query('type'), fn ($query, $type) => $query->where('type', $type))
            ->when($request->query('search'), function ($query, string $search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('brand', 'like', "%{$search}%")
                        ->orWhere('provider_reference', 'like', "%{$search}%")
                        ->orWhere('last_four', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('is_default')
            ->orderBy('brand')
            ->paginate($request->integer('per_page', 20));

        return PaymentMethodResource::collection($methods)->response();
    }

    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        $method = $this->financeService->createPaymentMethod($request->validated());

        return PaymentMethodResource::make($method)->response()->setStatusCode(201);
    }

    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        return PaymentMethodResource::make($paymentMethod->load('member'))->response();
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $method = $this->financeService->updatePaymentMethod($paymentMethod, $request->validated());

        return PaymentMethodResource::make($method)->response();
    }

    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        $this->financeService->deletePaymentMethod($paymentMethod);

        return response()->json([], 204);
    }
}
