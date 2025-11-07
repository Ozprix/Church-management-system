<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePledgeRequest;
use App\Http\Requests\Finance\UpdatePledgeRequest;
use App\Http\Resources\Finance\PledgeResource;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\Pledge;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PledgeController extends Controller
{
    public function __construct(private readonly FinanceService $financeService)
    {
        $this->middleware('feature:finance');
        $this->middleware('can:finance.manage_pledges');
    }

    public function index(Request $request): JsonResponse
    {
        $pledges = Pledge::query()
            ->with(['member', 'fund'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('member_id'), fn ($query, $memberId) => $query->where('member_id', $memberId))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return PledgeResource::collection($pledges)->response();
    }

    public function store(StorePledgeRequest $request): JsonResponse
    {
        $pledge = $this->financeService->createPledge($request->validated());

        return PledgeResource::make($pledge->load(['member', 'fund']))->response()->setStatusCode(201);
    }

    public function show(Pledge $pledge): JsonResponse
    {
        return PledgeResource::make($pledge->load(['member', 'fund']))->response();
    }

    public function update(UpdatePledgeRequest $request, Pledge $pledge): JsonResponse
    {
        $pledge = $this->financeService->updatePledge($pledge, $request->validated());

        return PledgeResource::make($pledge->load(['member', 'fund']))->response();
    }

    public function destroy(Pledge $pledge): JsonResponse
    {
        $pledge->delete();

        return response()->json([], 204);
    }

    public function reminders(Request $request, Pledge $pledge): JsonResponse
    {
        $notifications = Notification::query()
            ->where('tenant_id', $pledge->tenant_id)
            ->whereJsonContains('payload->pledge_id', $pledge->id)
            ->when($request->query('channel'), fn ($query, $channel) => $query->where('channel', $channel))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('recipient'), fn ($query, $recipient) => $query->where('recipient', 'like', "%{$recipient}%"))
            ->when($request->query('q'), function ($query) use ($request): void {
                $term = '%' . $request->query('q') . '%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('recipient', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhere('body', 'like', $term);
                });
            })
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return NotificationResource::collection($notifications)->response();
    }
}
