<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreDonationRequest;
use App\Http\Requests\Finance\UpdateDonationRequest;
use App\Http\Resources\Finance\DonationResource;
use App\Models\Donation;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonationController extends Controller
{
    public function __construct(private readonly FinanceService $financeService)
    {
        $this->middleware('feature:finance');
        $this->middleware('can:finance.manage_donations');
    }

    public function index(Request $request): JsonResponse
    {
        $donations = Donation::query()
            ->with(['member', 'items.fund'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('member_id'), fn ($query, $memberId) => $query->where('member_id', $memberId))
            ->when($request->query('from'), fn ($query, $from) => $query->whereDate('received_at', '>=', $from))
            ->when($request->query('to'), fn ($query, $to) => $query->whereDate('received_at', '<=', $to))
            ->orderByDesc('received_at')
            ->paginate($request->integer('per_page', 25));

        return DonationResource::collection($donations)->response();
    }

    public function store(StoreDonationRequest $request): JsonResponse
    {
        $donation = $this->financeService->recordDonation($request->validated());

        return DonationResource::make($donation)->response()->setStatusCode(201);
    }

    public function show(Donation $donation): JsonResponse
    {
        return DonationResource::make($donation->load(['member', 'items.fund']))->response();
    }

    public function update(UpdateDonationRequest $request, Donation $donation): JsonResponse
    {
        $donation = $this->financeService->updateDonation($donation, $request->validated());

        return DonationResource::make($donation)->response();
    }

    public function destroy(Donation $donation): JsonResponse
    {
        $donation->delete();

        return response()->json([], 204);
    }
}
