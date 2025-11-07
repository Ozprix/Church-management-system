<?php

namespace App\Console\Commands;

use App\Models\AttendanceAnalyticsReport;
use App\Services\Analytics\AttendanceAnalyticsService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RunAttendanceAnalyticsReportsCommand extends Command
{
    protected $signature = 'attendance:run-saved-reports';

    protected $description = 'Evaluate scheduled attendance analytics reports and deliver outputs.';

    public function handle(AttendanceAnalyticsService $analyticsService, NotificationService $notificationService): int
    {
        $now = Carbon::now();

        AttendanceAnalyticsReport::query()
            ->where('frequency', '!=', 'none')
            ->with('tenant', 'owner')
            ->chunkById(25, function ($reports) use ($analyticsService, $notificationService, $now) {
                foreach ($reports as $report) {
                    if (! $report->tenant) {
                        continue;
                    }

                    if (! $this->isDue($report, $now)) {
                        continue;
                    }

                    $filters = $analyticsService->parseFilters($report->filters ?? []);
                    $metrics = $analyticsService->metrics($report->tenant_id, $filters);

                    if (in_array($report->channel, ['email', 'both'], true)) {
                        $recipient = $report->email_recipient
                            ?? $report->owner?->email
                            ?? $report->tenant->users()->value('email');

                        if ($recipient) {
                            $summary = sprintf(
                                "Gatherings tracked: %d\nTotal check-ins: %d\nAttendance rate: %s%%",
                                $metrics['summary']['gatherings_tracked'] ?? 0,
                                $metrics['summary']['total_check_ins'] ?? 0,
                                $metrics['summary']['attendance_rate'] ?? 0
                            );

                            $notificationService->queue([
                                'tenant_id' => $report->tenant_id,
                                'channel' => 'email',
                                'recipient' => $recipient,
                                'subject' => 'Scheduled attendance analytics report: ' . $report->name,
                                'body' => $summary,
                            ]);
                        }
                    }

                    if ($this->shouldCreateSnapshot($report->channel)) {
                        $this->storeSnapshot($report, $filters, $analyticsService, $now);
                    }

                    $report->forceFill(['last_run_at' => $now])->save();
                }
            });

        $this->info('Scheduled attendance analytics reports processed.');

        return self::SUCCESS;
    }

    private function isDue(AttendanceAnalyticsReport $report, Carbon $now): bool
    {
        if (! $report->isScheduled()) {
            return false;
        }

        if (! $report->last_run_at) {
            return true;
        }

        return match ($report->frequency) {
            'daily' => $report->last_run_at->diffInDays($now) >= 1,
            'weekly' => $report->last_run_at->diffInWeeks($now) >= 1,
            'monthly' => $report->last_run_at->diffInMonths($now) >= 1,
            default => false,
        };
    }

    private function shouldCreateSnapshot(string $channel): bool
    {
        return in_array($channel, ['download', 'both'], true);
    }

    private function storeSnapshot(
        AttendanceAnalyticsReport $report,
        array $filters,
        AttendanceAnalyticsService $analyticsService,
        Carbon $generatedAt
    ): void {
        $disk = config('attendance_reports.disk', 'reports');
        $directory = trim(config('attendance_reports.directory', 'attendance'), '/');
        $ttlDays = (int) config('attendance_reports.download_ttl_days', 7);
        $expiresAt = $ttlDays > 0 ? $generatedAt->copy()->addDays($ttlDays) : null;

        $rows = $analyticsService->gatheringSummaries($report->tenant_id, $filters);
        $csv = $this->renderCsv($rows);

        $slug = Str::slug($report->name) ?: 'attendance-report';
        $filename = sprintf(
            '%s-%s.csv',
            $slug,
            $generatedAt->format('Ymd_His')
        );
        $path = "{$directory}/{$report->id}/{$filename}";

        Storage::disk($disk)->put($path, $csv);

        $report->snapshots()->create([
            'tenant_id' => $report->tenant_id,
            'disk' => $disk,
            'file_path' => $path,
            'format' => 'csv',
            'filters' => $this->normaliseFiltersForStorage($filters),
            'generated_at' => $generatedAt,
            'expires_at' => $expiresAt,
        ]);
    }

    private function renderCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'wb+');

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
                $startsAt ? optional(Carbon::make($startsAt))->toDateTimeString() : '',
                $row['present'] ?? 0,
                $row['absent'] ?? 0,
                $row['excused'] ?? 0,
                $row['attendance_rate'] ?? 0,
            ]);
        }

        rewind($handle);
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $contents;
    }

    private function normaliseFiltersForStorage(array $filters): array
    {
        return [
            'from' => isset($filters['from']) && $filters['from'] instanceof Carbon ? $filters['from']->toDateString() : null,
            'to' => isset($filters['to']) && $filters['to'] instanceof Carbon ? $filters['to']->toDateString() : null,
            'service_id' => $filters['service_id'] ?? null,
            'status' => $filters['status'] ?? null,
        ];
    }
}
