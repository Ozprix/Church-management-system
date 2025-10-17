<?php

namespace App\Http\Controllers\Api\Membership;

use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\StoreMembershipProcessRequest;
use App\Http\Requests\Membership\UpdateMembershipProcessRequest;
use App\Http\Resources\MembershipProcessResource;
use App\Models\MembershipProcess;
use App\Services\MembershipProcessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipProcessController extends Controller
{
    public function __construct(private readonly MembershipProcessService $service)
    {
        $this->middleware('feature:membership_processes');
        $this->middleware('can:membership_processes.manage');
    }

    public function index(Request $request): JsonResponse
    {
        $processes = MembershipProcess::query()
            ->with('stages')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20))
            ->appends($request->query());

        return MembershipProcessResource::collection($processes)->response();
    }

    public function store(StoreMembershipProcessRequest $request): JsonResponse
    {
        $process = $this->service->createProcess($request->validated() + [
            'tenant_id' => optional($request->attributes->get('tenant'))->id ?? $request->user()->tenant_id,
        ]);

        return MembershipProcessResource::make($process)->response()->setStatusCode(201);
    }

    public function show(MembershipProcess $membershipProcess): JsonResponse
    {
        return MembershipProcessResource::make($membershipProcess->load('stages'))->response();
    }

    public function update(UpdateMembershipProcessRequest $request, MembershipProcess $membershipProcess): JsonResponse
    {
        $process = $this->service->updateProcess($membershipProcess, $request->validated());

        return MembershipProcessResource::make($process)->response();
    }

    public function destroy(MembershipProcess $membershipProcess): JsonResponse
    {
        $membershipProcess->delete();

        return response()->json([], 204);
    }
}
