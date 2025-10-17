<?php

namespace App\Http\Controllers\Api\Membership;

use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\AdvanceMemberProcessRequest;
use App\Http\Requests\Membership\StartMemberProcessRequest;
use App\Http\Resources\MemberProcessRunResource;
use App\Models\Member;
use App\Models\MemberProcessRun;
use App\Models\MembershipProcess;
use App\Models\MembershipProcessStage;
use App\Services\MembershipProcessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberProcessRunController extends Controller
{
    public function __construct(private readonly MembershipProcessService $service)
    {
        $this->middleware('feature:membership_processes');
        $this->middleware('can:membership_processes.run');
    }

    public function index(Request $request): JsonResponse
    {
        $runs = MemberProcessRun::query()
            ->with(['process', 'currentStage'])
            ->when($request->query('member_id'), fn ($query, $memberId) => $query->where('member_id', $memberId))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20))
            ->appends($request->query());

        return MemberProcessRunResource::collection($runs)->response();
    }

    public function store(StartMemberProcessRequest $request, MembershipProcess $membershipProcess): JsonResponse
    {
        $member = Member::query()->forTenant($membershipProcess->tenant_id)->findOrFail($request->integer('member_id'));

        $run = $this->service->startProcess($member, $membershipProcess);

        return MemberProcessRunResource::make($run->load(['process', 'currentStage']))->response()->setStatusCode(201);
    }

    public function advance(AdvanceMemberProcessRequest $request, MemberProcessRun $memberProcessRun): JsonResponse
    {
        $nextStageId = $request->input('next_stage_id');
        $nextStage = null;

        if ($nextStageId) {
            $nextStage = MembershipProcessStage::query()->findOrFail($nextStageId);
        }

        $run = $this->service->advance($memberProcessRun, $nextStage, $request->input('notes'));

        return MemberProcessRunResource::make($run->load(['process', 'currentStage', 'logs']))->response();
    }

    public function halt(MemberProcessRun $memberProcessRun, Request $request): JsonResponse
    {
        $run = $this->service->halt($memberProcessRun, $request->input('notes'));

        return MemberProcessRunResource::make($run)->response();
    }
}
