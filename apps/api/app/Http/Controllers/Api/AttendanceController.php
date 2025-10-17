<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\BulkAttendanceRequest;
use App\Http\Requests\Attendance\RecordAttendanceRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use App\Models\Gathering;
use App\Models\Member;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService)
    {
        $this->middleware('feature:attendance');
        $this->middleware('can:attendance.view')->only(['index']);
        $this->middleware('can:attendance.manage')->only(['store', 'update', 'bulk', 'destroy']);
    }

    public function index(Request $request, Gathering $gathering): JsonResponse
    {
        $records = $gathering->attendanceRecords()
            ->with('member')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 25));

        return AttendanceRecordResource::collection($records)->response();
    }

    public function store(RecordAttendanceRequest $request, Gathering $gathering): JsonResponse
    {
        $member = Member::query()
            ->where('tenant_id', $gathering->tenant_id)
            ->findOrFail($request->integer('member_id'));

        $record = $this->attendanceService->recordAttendance($gathering, $member, $request->validated());

        return AttendanceRecordResource::make($record->load('member'))->response()->setStatusCode(201);
    }

    public function update(RecordAttendanceRequest $request, Gathering $gathering, AttendanceRecord $attendanceRecord): JsonResponse
    {
        $this->ensureSameGathering($gathering, $attendanceRecord);

        $member = $attendanceRecord->member ?? Member::query()
            ->where('tenant_id', $gathering->tenant_id)
            ->findOrFail($request->integer('member_id', $attendanceRecord->member_id));

        $record = $this->attendanceService->recordAttendance($gathering, $member, $request->validated());

        return AttendanceRecordResource::make($record->load('member'))->response();
    }

    public function bulk(BulkAttendanceRequest $request, Gathering $gathering): JsonResponse
    {
        $this->attendanceService->bulkRecordAttendance($gathering, $request->validated('member_ids'), $request->validated('status', 'present'));

        return response()->json(['message' => 'Attendance updated.']);
    }

    public function destroy(Gathering $gathering, AttendanceRecord $attendanceRecord): JsonResponse
    {
        $this->ensureSameGathering($gathering, $attendanceRecord);

        $attendanceRecord->delete();

        return response()->json([], 204);
    }

    private function ensureSameGathering(Gathering $gathering, AttendanceRecord $attendanceRecord): void
    {
        if ($attendanceRecord->gathering_id !== $gathering->id) {
            abort(404);
        }
    }
}
