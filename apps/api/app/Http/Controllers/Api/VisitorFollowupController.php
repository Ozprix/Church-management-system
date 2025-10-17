<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Visitor\StartVisitorFollowupRequest;
use App\Http\Resources\VisitorFollowupResource;
use App\Models\Member;
use App\Models\VisitorFollowup;
use App\Models\VisitorWorkflow;
use App\Services\VisitorAutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorFollowupController extends Controller
{
    public function __construct(private readonly VisitorAutomationService $automationService)
    {
        $this->middleware('feature:visitors');
        $this->middleware('can:visitors.manage_followups');
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $followups = VisitorFollowup::query()
            ->with(['workflow', 'currentStep'])
            ->when($tenant, fn ($query) => $query->where('tenant_id', $tenant->id))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20))
            ->appends($request->query());

        return VisitorFollowupResource::collection($followups)->response();
    }

    public function store(StartVisitorFollowupRequest $request): VisitorFollowupResource
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant?->id;

        $memberQuery = Member::query();
        $workflowQuery = VisitorWorkflow::query()->with('steps');

        if ($tenantId) {
            $memberQuery->where('tenant_id', $tenantId);
            $workflowQuery->where('tenant_id', $tenantId);
        }

        $member = $memberQuery->findOrFail($request->integer('member_id'));
        $workflow = $workflowQuery->findOrFail($request->integer('workflow_id'));

        $followup = $this->automationService->startFollowup($member, $workflow);

        return VisitorFollowupResource::make($followup->load(['workflow', 'currentStep']));
    }

    public function update(VisitorFollowup $visitorFollowup): VisitorFollowupResource
    {
        $updated = $this->automationService->haltFollowup($visitorFollowup);

        return VisitorFollowupResource::make($updated);
    }
}
