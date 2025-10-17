<?php

namespace App\Http\Controllers\Api\Volunteer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Volunteer\StoreVolunteerHourRequest;
use App\Http\Resources\VolunteerHourResource;
use App\Models\VolunteerHour;
use App\Services\VolunteerPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VolunteerHourController extends Controller
{
    public function __construct(private readonly VolunteerPipelineService $service)
    {
        $this->middleware('feature:volunteer_pipeline');
        $this->middleware('can:volunteer_pipeline.manage_hours');
    }

    public function index(Request $request): JsonResponse
    {
        $hours = VolunteerHour::query()
            ->with(['member', 'assignment'])
            ->when($request->query('member_id'), fn ($query, $memberId) => $query->where('member_id', $memberId))
            ->orderByDesc('served_on')
            ->paginate($request->integer('per_page', 25))
            ->appends($request->query());

        return VolunteerHourResource::collection($hours)->response();
    }

    public function store(StoreVolunteerHourRequest $request): JsonResponse
    {
        $hour = $this->service->recordHours($request->validated());

        return VolunteerHourResource::make($hour->load(['member', 'assignment']))->response()->setStatusCode(201);
    }
}
