<?php

namespace App\Http\Controllers\Api\Volunteer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Volunteer\StoreVolunteerAssignmentRequest;
use App\Http\Requests\Volunteer\SwapVolunteerAssignmentRequest;
use App\Http\Requests\Volunteer\UpdateVolunteerAssignmentRequest;
use App\Http\Resources\VolunteerAssignmentResource;
use App\Models\VolunteerAssignment;
use App\Services\VolunteerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VolunteerAssignmentController extends Controller
{
    public function __construct(private readonly VolunteerService $volunteerService)
    {
        $this->middleware('feature:volunteers');
        $this->middleware('can:volunteers.view')->only(['index', 'show']);
        $this->middleware('can:volunteers.manage_assignments')->only(['store', 'update', 'swap', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $assignments = VolunteerAssignment::query()
            ->with(['member', 'role', 'team', 'gathering'])
            ->when($request->query('member_id'), fn ($query, $memberId) => $query->where('member_id', $memberId))
            ->when($request->query('role_id'), fn ($query, $roleId) => $query->where('volunteer_role_id', $roleId))
            ->when($request->query('team_id'), fn ($query, $teamId) => $query->where('volunteer_team_id', $teamId))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('starts_at')
            ->paginate($request->integer('per_page', 25));

        return VolunteerAssignmentResource::collection($assignments)->response();
    }

    public function store(StoreVolunteerAssignmentRequest $request): JsonResponse
    {
        $assignment = $this->volunteerService->assign($request->validated());

        return VolunteerAssignmentResource::make($assignment)->response()->setStatusCode(201);
    }

    public function show(VolunteerAssignment $volunteerAssignment): JsonResponse
    {
        return VolunteerAssignmentResource::make($volunteerAssignment->load(['member', 'role', 'team', 'gathering']))->response();
    }

    public function update(UpdateVolunteerAssignmentRequest $request, VolunteerAssignment $volunteerAssignment): JsonResponse
    {
        $assignment = $this->volunteerService->updateAssignment($volunteerAssignment, $request->validated());

        return VolunteerAssignmentResource::make($assignment)->response();
    }

    public function swap(SwapVolunteerAssignmentRequest $request, VolunteerAssignment $volunteerAssignment): JsonResponse
    {
        $target = VolunteerAssignment::query()
            ->where('tenant_id', $volunteerAssignment->tenant_id)
            ->findOrFail($request->integer('target_assignment_id'));

        $this->volunteerService->swapAssignment($volunteerAssignment, $target);

        return response()->json(['message' => 'Assignments swapped.']);
    }

    public function destroy(VolunteerAssignment $volunteerAssignment): JsonResponse
    {
        $volunteerAssignment->delete();

        return response()->json([], 204);
    }
}
