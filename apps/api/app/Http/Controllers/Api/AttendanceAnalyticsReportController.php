<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\StoreAttendanceAnalyticsReportRequest;
use App\Http\Requests\Analytics\UpdateAttendanceAnalyticsReportRequest;
use App\Http\Resources\AttendanceAnalyticsReportResource;
use App\Models\AttendanceAnalyticsReport;
use App\Services\Analytics\AttendanceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceAnalyticsReportController extends Controller
{
    public function __construct(private readonly AttendanceAnalyticsService $analyticsService)
    {
        $this->middleware('feature:attendance');
        $this->middleware('can:attendance.view');
    }

    public function index(Request $request): JsonResponse
    {
        $reports = AttendanceAnalyticsReport::query()
            ->where('tenant_id', optional($request->attributes->get('tenant'))->id)
            ->withCount('snapshots')
            ->with(['latestSnapshot'])
            ->orderBy('name')
            ->get();

        return AttendanceAnalyticsReportResource::collection($reports)->response();
    }

    public function store(StoreAttendanceAnalyticsReportRequest $request): JsonResponse
    {
        $report = AttendanceAnalyticsReport::create($request->validated());

        return AttendanceAnalyticsReportResource::make($report)->response()->setStatusCode(201);
    }

    public function show(AttendanceAnalyticsReport $attendanceAnalyticsReport): JsonResponse
    {
        $this->authorizeReport($attendanceAnalyticsReport);

        return AttendanceAnalyticsReportResource::make($attendanceAnalyticsReport)->response();
    }

    public function update(UpdateAttendanceAnalyticsReportRequest $request, AttendanceAnalyticsReport $attendanceAnalyticsReport): JsonResponse
    {
        $this->authorizeReport($attendanceAnalyticsReport);

        $attendanceAnalyticsReport->fill($request->validated());
        $attendanceAnalyticsReport->save();

        return AttendanceAnalyticsReportResource::make($attendanceAnalyticsReport)->response();
    }

    public function destroy(AttendanceAnalyticsReport $attendanceAnalyticsReport): JsonResponse
    {
        $this->authorizeReport($attendanceAnalyticsReport);

        $attendanceAnalyticsReport->delete();

        return response()->json([], 204);
    }

    public function run(AttendanceAnalyticsReport $attendanceAnalyticsReport): JsonResponse
    {
        $this->authorizeReport($attendanceAnalyticsReport);

        $filters = $this->analyticsService->parseFilters($attendanceAnalyticsReport->filters ?? []);
        $metrics = $this->analyticsService->metrics($attendanceAnalyticsReport->tenant_id, $filters);

        return response()->json($metrics);
    }

    public function export(AttendanceAnalyticsReport $attendanceAnalyticsReport): StreamedResponse
    {
        $this->authorizeReport($attendanceAnalyticsReport);

        $filters = $this->analyticsService->parseFilters($attendanceAnalyticsReport->filters ?? []);
        $rows = $this->analyticsService->gatheringSummaries($attendanceAnalyticsReport->tenant_id, $filters);
        $filename = 'attendance-report-' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'Gathering',
                'Service',
                'Starts at',
                'Present',
                'Absent',
                'Excused',
                'Attendance rate (%)',
            ]);

            foreach ($rows as $row) {
                $startsAt = $row['starts_at'] ?? null;

                fputcsv($handle, [
                    $row['name'] ?? '',
                    $row['service'] ?? '',
                    $startsAt ? optional(\Carbon\Carbon::make($startsAt))->toDateTimeString() : '',
                    $row['present'] ?? 0,
                    $row['absent'] ?? 0,
                    $row['excused'] ?? 0,
                    $row['attendance_rate'] ?? 0,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function authorizeReport(AttendanceAnalyticsReport $report): void
    {
        $tenant = request()->attributes->get('tenant');
        abort_unless($tenant && $report->tenant_id === $tenant->id, 404);
    }
}
