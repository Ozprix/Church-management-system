<?php

namespace App\Services\Attendance;

use App\Models\Gathering;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

class AttendanceExportService
{
    public function streamCsv(Gathering $gathering, $handle): void
    {
        $summary = $this->summarize($gathering);
        $rows = $this->buildRows($gathering);

        fputcsv($handle, ['Summary', 'Value']);
        fputcsv($handle, ['Present', $summary['present'] ?? 0]);
        fputcsv($handle, ['Absent', $summary['absent'] ?? 0]);
        fputcsv($handle, ['Excused', $summary['excused'] ?? 0]);
        fputcsv($handle, ['Attendance rate (%)', $summary['attendance_rate'] ?? 0]);
        fputcsv($handle, []); // spacer row

        fputcsv($handle, $this->csvHeaders());

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['member_name'],
                $row['status_label'],
                $row['checked_in_at'],
                $row['checked_out_at'],
                $row['check_in_method'],
                $row['notes'],
            ]);
        }
    }

    public function csvString(Gathering $gathering): string
    {
        $handle = fopen('php://temp', 'r+');
        $this->streamCsv($gathering, $handle);
        rewind($handle);

        return stream_get_contents($handle) ?: '';
    }

    public function pdf(Gathering $gathering): string
    {
        $summary = $this->summarize($gathering);
        $rows = $this->buildRows($gathering);

        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $html = view('exports.attendance.gathering', [
            'gathering' => $gathering,
            'summary' => $summary,
            'rows' => $rows,
        ])->render();

        $mpdf = new Mpdf([
            'tempDir' => $tempDir,
        ]);
        $mpdf->SetTitle(sprintf('Attendance - %s', $gathering->name ?? 'Gathering'));
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    public function summarize(Gathering $gathering): array
    {
        $records = $this->attendanceRecords($gathering);
        $present = $records->where('status', 'present')->count();
        $absent = $records->where('status', 'absent')->count();
        $excused = $records->where('status', 'excused')->count();
        $total = $records->count();

        return [
            'present' => $present,
            'absent' => $absent,
            'excused' => $excused,
            'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0.0,
        ];
    }

    public function filenameForGathering(Gathering $gathering, string $extension = 'csv'): string
    {
        $slug = Str::slug($gathering->name ?: 'gathering');

        return sprintf('%s-%s.%s', $slug ?: 'gathering', $gathering->id, $extension);
    }

    private function csvHeaders(): array
    {
        return [
            'Member',
            'Status',
            'Checked in at',
            'Checked out at',
            'Check-in method',
            'Notes',
        ];
    }

    /**
     * @return array<int, array{
     *     member_name: string,
     *     status: string,
     *     status_label: string,
     *     checked_in_at: string|null,
     *     checked_out_at: string|null,
     *     check_in_method: string|null,
     *     notes: string|null
     * }>
     */
    private function buildRows(Gathering $gathering): array
    {
        return $this->attendanceRecords($gathering)
            ->map(function ($record) {
                $memberName = $record->member
                    ? trim(($record->member->first_name ?? '') . ' ' . ($record->member->last_name ?? ''))
                    : 'Guest';

                return [
                    'member_name' => $memberName !== '' ? $memberName : 'Guest',
                    'status' => $record->status,
                    'status_label' => ucfirst($record->status ?? 'unknown'),
                    'checked_in_at' => optional($record->checked_in_at)->toDateTimeString(),
                    'checked_out_at' => optional($record->checked_out_at)->toDateTimeString(),
                    'check_in_method' => $record->check_in_method,
                    'notes' => $this->formatNotes($record->notes ?? []),
                ];
            })
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\AttendanceRecord>
     */
    private function attendanceRecords(Gathering $gathering): Collection
    {
        return $gathering->attendanceRecords()
            ->with('member:id,first_name,last_name')
            ->orderByRaw("CASE status WHEN 'present' THEN 1 WHEN 'excused' THEN 2 ELSE 3 END")
            ->orderBy('checked_in_at')
            ->orderBy('created_at')
            ->get();
    }

    private function formatNotes($notes): ?string
    {
        if (is_string($notes)) {
            return $notes;
        }

        if (is_array($notes)) {
            return collect($notes)->map(fn ($value, $key) => is_string($key) ? "{$key}: {$value}" : (string) $value)->implode('; ');
        }

        return null;
    }
}
