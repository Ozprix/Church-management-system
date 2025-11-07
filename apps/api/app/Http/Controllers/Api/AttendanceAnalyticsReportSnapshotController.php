<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceAnalyticsReportSnapshotResource;
use App\Models\AttendanceAnalyticsReport;
use App\Models\AttendanceAnalyticsReportSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceAnalyticsReportSnapshotController extends Controller
{
    public function __construct()
    {
        $this->middleware('feature:attendance');
        $this->middleware('can:attendance.view');
    }

    public function index(Request $request, AttendanceAnalyticsReport $attendanceAnalyticsReport): JsonResponse
    {
        $this->authorizeReport($attendanceAnalyticsReport, $request);

        $snapshots = $attendanceAnalyticsReport->snapshots()
            ->latest('generated_at')
            ->get();

        return AttendanceAnalyticsReportSnapshotResource::collection($snapshots)->response();
    }

    public function download(
        Request $request,
        AttendanceAnalyticsReport $attendanceAnalyticsReport,
        AttendanceAnalyticsReportSnapshot $snapshot
    ): StreamedResponse {
        $this->authorizeReport($attendanceAnalyticsReport, $request);

        abort_unless($snapshot->attendance_analytics_report_id === $attendanceAnalyticsReport->id, 404);

        if ($snapshot->expires_at && $snapshot->expires_at->isPast()) {
            abort(410, __('This snapshot has expired. Generate a new report to download fresh data.'));
        }

        $disk = $snapshot->disk;
        $path = $snapshot->file_path;

        if (! Storage::disk($disk)->exists($path)) {
            abort(404, __('Snapshot file is no longer available.'));
        }

        $filename = sprintf(
            'attendance-report-%s.%s',
            optional($snapshot->generated_at)?->format('Ymd_His') ?? $snapshot->id,
            $snapshot->format
        );

        return Storage::disk($disk)->download($path, $filename);
    }

    private function authorizeReport(AttendanceAnalyticsReport $report, Request $request): void
    {
        $tenant = $request->attributes->get('tenant');
        abort_unless($tenant && $report->tenant_id === $tenant->id, 404);
    }
}

