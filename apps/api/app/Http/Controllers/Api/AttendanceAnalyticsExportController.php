<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AttendanceAnalyticsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceAnalyticsExportController extends Controller
{
    public function __construct(private readonly AttendanceAnalyticsService $analytics)
    {
        $this->middleware('feature:attendance');
        $this->middleware('can:attendance.view');
    }

    public function __invoke(Request $request): StreamedResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant?->id ?? $request->user()?->tenant_id;

        if (! $tenantId) {
            abort(422, __('Unable to resolve tenant for analytics export.'));
        }

        $filters = $this->analytics->parseFilters($request->query());
        $rows = $this->analytics->gatheringSummaries((int) $tenantId, $filters);
        $filename = 'attendance-analytics-' . now()->format('Ymd_His') . '.csv';

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
}
