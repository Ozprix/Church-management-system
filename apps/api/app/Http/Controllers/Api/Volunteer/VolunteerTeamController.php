<?php

namespace App\Http\Controllers\Api\Volunteer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Volunteer\StoreVolunteerTeamRequest;
use App\Http\Requests\Volunteer\UpdateVolunteerTeamRequest;
use App\Http\Resources\VolunteerTeamResource;
use App\Models\VolunteerTeam;
use App\Services\VolunteerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VolunteerTeamController extends Controller
{
    public function __construct(private readonly VolunteerService $volunteerService)
    {
        $this->middleware('feature:volunteers');
        $this->middleware('can:volunteers.view')->only(['index', 'show']);
        $this->middleware('can:volunteers.manage_teams')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $teams = VolunteerTeam::query()
            ->with('roles')
            ->when($request->query('search'), function ($query, string $search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return VolunteerTeamResource::collection($teams)->response();
    }

    public function store(StoreVolunteerTeamRequest $request): JsonResponse
    {
        $team = $this->volunteerService->createTeam($request->validated());

        return VolunteerTeamResource::make($team)->response()->setStatusCode(201);
    }

    public function show(VolunteerTeam $volunteerTeam): JsonResponse
    {
        return VolunteerTeamResource::make($volunteerTeam->load('roles'))->response();
    }

    public function update(UpdateVolunteerTeamRequest $request, VolunteerTeam $volunteerTeam): JsonResponse
    {
        $team = $this->volunteerService->updateTeam($volunteerTeam, $request->validated());

        return VolunteerTeamResource::make($team)->response();
    }

    public function destroy(VolunteerTeam $volunteerTeam): JsonResponse
    {
        $volunteerTeam->delete();

        return response()->json([], 204);
    }
}
