<?php

namespace App\Http\Controllers\Api\Volunteer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Volunteer\UpdateVolunteerAvailabilityRequest;
use App\Http\Resources\VolunteerAvailabilityResource;
use App\Models\VolunteerAvailability;
use App\Services\VolunteerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VolunteerAvailabilityController extends Controller
{
    public function __construct(private readonly VolunteerService $volunteerService)
    {
        $this->middleware('feature:volunteers');
        $this->middleware('can:volunteers.view')->only(['index']);
        $this->middleware('can:volunteers.manage_availability')->only(['store', 'update']);
    }

    public function index(Request $request): JsonResponse
    {
        $availability = VolunteerAvailability::query()
            ->with('member')
            ->when($request->query('member_id'), fn ($query, $memberId) => $query->where('member_id', $memberId))
            ->orderBy('updated_at', 'desc')
            ->paginate($request->integer('per_page', 25));

        return VolunteerAvailabilityResource::collection($availability)->response();
    }

    public function update(UpdateVolunteerAvailabilityRequest $request, VolunteerAvailability $volunteerAvailability): JsonResponse
    {
        $availability = $this->volunteerService->updateAvailability(
            array_merge($request->validated(), [
                'tenant_id' => $volunteerAvailability->tenant_id,
                'member_id' => $volunteerAvailability->member_id,
            ])
        );

        return VolunteerAvailabilityResource::make($availability->load('member'))->response();
    }

    public function store(UpdateVolunteerAvailabilityRequest $request): JsonResponse
    {
        $availability = $this->volunteerService->updateAvailability($request->validated());

        return VolunteerAvailabilityResource::make($availability->load('member'))->response()->setStatusCode(201);
    }
}
