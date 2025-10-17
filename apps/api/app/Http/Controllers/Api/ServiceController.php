<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService)
    {
        $this->middleware('feature:attendance');
        $this->middleware('can:attendance.view')->only(['index', 'show']);
        $this->middleware('can:services.manage')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $services = Service::query()
            ->when($request->query('search'), function ($query, string $search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('short_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return ServiceResource::collection($services)->response();
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $this->attendanceService->createService($request->validated());

        return ServiceResource::make($service)->response()->setStatusCode(201);
    }

    public function show(Service $service): JsonResponse
    {
        return ServiceResource::make($service)->response();
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $service = $this->attendanceService->updateService($service, $request->validated());

        return ServiceResource::make($service)->response();
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json([], 204);
    }
}
