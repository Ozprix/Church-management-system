<?php

namespace App\Http\Controllers\Api\Volunteer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Volunteer\StoreVolunteerSignupRequest;
use App\Http\Requests\Volunteer\UpdateVolunteerSignupRequest;
use App\Http\Resources\VolunteerSignupResource;
use App\Models\VolunteerSignup;
use App\Services\VolunteerPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VolunteerSignupController extends Controller
{
    public function __construct(private readonly VolunteerPipelineService $service)
    {
        $this->middleware('feature:volunteer_pipeline');
        $this->middleware('can:volunteer_pipeline.manage_signups');
    }

    public function index(Request $request): JsonResponse
    {
        $signups = VolunteerSignup::query()
            ->with(['member', 'role', 'team'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('applied_at')
            ->paginate($request->integer('per_page', 20))
            ->appends($request->query());

        return VolunteerSignupResource::collection($signups)->response();
    }

    public function store(StoreVolunteerSignupRequest $request): JsonResponse
    {
        $signup = $this->service->submitSignup($request->validated());

        return VolunteerSignupResource::make($signup->load(['member', 'role', 'team']))->response()->setStatusCode(201);
    }

    public function show(VolunteerSignup $volunteerSignup): JsonResponse
    {
        return VolunteerSignupResource::make($volunteerSignup->load(['member', 'role', 'team']))->response();
    }

    public function update(UpdateVolunteerSignupRequest $request, VolunteerSignup $volunteerSignup): JsonResponse
    {
        $signup = $this->service->updateSignup($volunteerSignup, $request->validated());

        return VolunteerSignupResource::make($signup->load(['member', 'role', 'team']))->response();
    }

    public function destroy(VolunteerSignup $volunteerSignup): JsonResponse
    {
        $this->service->deleteSignup($volunteerSignup);

        return response()->json([], 204);
    }
}
