<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gathering\StoreGatheringRequest;
use App\Http\Requests\Gathering\UpdateGatheringRequest;
use App\Http\Resources\GatheringResource;
use App\Models\Gathering;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GatheringController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService)
    {
        $this->middleware('feature:attendance');
        $this->middleware('can:attendance.view')->only(['index', 'show']);
        $this->middleware('can:gatherings.manage')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $gatherings = Gathering::query()
            ->with('service')
            ->withCount([
                'attendanceRecords as attendance_total_count',
                'attendanceRecords as attendance_present_count' => fn ($query) => $query->where('status', 'present'),
                'attendanceRecords as attendance_absent_count' => fn ($query) => $query->where('status', 'absent'),
                'attendanceRecords as attendance_excused_count' => fn ($query) => $query->where('status', 'excused'),
            ])
            ->when($request->query('service_id'), fn ($query, $serviceId) => $query->where('service_id', $serviceId))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('from'), fn ($query, $from) => $query->whereDate('starts_at', '>=', $from))
            ->when($request->query('to'), fn ($query, $to) => $query->whereDate('starts_at', '<=', $to))
            ->orderByDesc('starts_at')
            ->paginate($request->integer('per_page', 15));

        return GatheringResource::collection($gatherings)->response();
    }

    public function store(StoreGatheringRequest $request): JsonResponse
    {
        $gathering = $this->attendanceService->scheduleGathering($request->validated());

        return GatheringResource::make($gathering->load('service'))->response()->setStatusCode(201);
    }

    public function show(Gathering $gathering): JsonResponse
    {
        $gathering->load(['service', 'attendanceRecords.member']);

        return GatheringResource::make($gathering)->response();
    }

    public function update(UpdateGatheringRequest $request, Gathering $gathering): JsonResponse
    {
        $gathering = $this->attendanceService->updateGathering($gathering, $request->validated());

        return GatheringResource::make($gathering->load('service'))->response();
    }

    public function destroy(Gathering $gathering): JsonResponse
    {
        $gathering->delete();

        return response()->json([], 204);
    }
}
