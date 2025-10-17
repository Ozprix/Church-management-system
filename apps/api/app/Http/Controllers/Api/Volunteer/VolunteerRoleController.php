<?php

namespace App\Http\Controllers\Api\Volunteer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Volunteer\StoreVolunteerRoleRequest;
use App\Http\Requests\Volunteer\UpdateVolunteerRoleRequest;
use App\Http\Resources\VolunteerRoleResource;
use App\Models\VolunteerRole;
use App\Services\VolunteerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VolunteerRoleController extends Controller
{
    public function __construct(private readonly VolunteerService $volunteerService)
    {
        $this->middleware('feature:volunteers');
        $this->middleware('can:volunteers.view')->only(['index', 'show']);
        $this->middleware('can:volunteers.manage_roles')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $roles = VolunteerRole::query()
            ->with('teams')
            ->when($request->query('search'), function ($query, string $search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return VolunteerRoleResource::collection($roles)->response();
    }

    public function store(StoreVolunteerRoleRequest $request): JsonResponse
    {
        $role = $this->volunteerService->createRole($request->validated());

        return VolunteerRoleResource::make($role)->response()->setStatusCode(201);
    }

    public function show(VolunteerRole $volunteerRole): JsonResponse
    {
        return VolunteerRoleResource::make($volunteerRole->load('teams'))->response();
    }

    public function update(UpdateVolunteerRoleRequest $request, VolunteerRole $volunteerRole): JsonResponse
    {
        $role = $this->volunteerService->updateRole($volunteerRole, $request->validated());

        return VolunteerRoleResource::make($role)->response();
    }

    public function destroy(VolunteerRole $volunteerRole): JsonResponse
    {
        $volunteerRole->delete();

        return response()->json([], 204);
    }
}
