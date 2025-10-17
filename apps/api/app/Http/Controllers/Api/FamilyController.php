<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Family\StoreFamilyRequest;
use App\Http\Requests\Family\UpdateFamilyRequest;
use App\Http\Resources\FamilyResource;
use App\Models\Family;
use App\Services\FamilyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    public function __construct(private readonly FamilyService $familyService)
    {
        $this->middleware('feature:members');
        $this->middleware('can:members.view')->only(['index', 'show']);
        $this->middleware('can:families.manage')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $families = Family::query()
            ->withCount('members')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return FamilyResource::collection($families)->response();
    }

    public function store(StoreFamilyRequest $request): JsonResponse
    {
        $family = $this->familyService->create($request->validated());

        return FamilyResource::make($family)->response()->setStatusCode(201);
    }

    public function show(Family $family): JsonResponse
    {
        $family->load('members');

        return FamilyResource::make($family)->response();
    }

    public function update(UpdateFamilyRequest $request, Family $family): JsonResponse
    {
        $family = $this->familyService->update($family, $request->validated());

        return FamilyResource::make($family)->response();
    }

    public function destroy(Family $family): JsonResponse
    {
        $family->delete();

        return response()->json([], 204);
    }
}
