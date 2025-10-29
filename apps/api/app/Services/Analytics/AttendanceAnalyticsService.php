<?php

namespace App\Services\Analytics;

use App\Models\AttendanceRecord;
use App\Models\Gathering;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceAnalyticsService
{
    public function metrics(int $tenantId): array
    {
        $windowStart = Carbon::now()->startOfWeek()->subWeeks(7);
        $gatherings = $this->fetchGatherings($tenantId, $windowStart);
        $summariesCollection = collect($this->summarizeGatherings($gatherings));

        $presentCount = (int) $summariesCollection->sum('present');
        $absentCount = (int) $summariesCollection->sum('absent');
        $excusedCount = (int) $summariesCollection->sum('excused');

        $totalRecords = $presentCount + $absentCount + $excusedCount;
        $totalGatherings = $summariesCollection->count();

        $attendanceRate = $totalRecords > 0
            ? round(($presentCount / $totalRecords) * 100, 1)
            : 0.0;

        $averageAttendance = $totalGatherings > 0
            ? round($presentCount / $totalGatherings, 1)
            : 0.0;

        $attendanceRecords = $gatherings->flatMap(fn (Gathering $gathering) => $gathering->attendanceRecords);
        $trend = $this->buildWeeklyTrend($attendanceRecords, $windowStart);

        $topGatherings = $summariesCollection
            ->sortByDesc('present')
            ->values()
            ->take(5)
            ->map(fn (array $row) => $row)
            ->all();

        $followupCandidates = $this->buildFollowupCandidates($tenantId, $windowStart);

        return [
            'summary' => [
                'gatherings_tracked' => $totalGatherings,
                'total_check_ins' => $presentCount,
                'attendance_rate' => $attendanceRate,
                'average_present' => $averageAttendance,
            ],
            'trend' => $trend,
            'top_gatherings' => $topGatherings,
            'followup_candidates' => $followupCandidates,
        ];
    }

    public function gatheringSummaries(int $tenantId, ?Carbon $windowStart = null): array
    {
        $windowStart ??= Carbon::now()->startOfWeek()->subWeeks(7);
        $gatherings = $this->fetchGatherings($tenantId, $windowStart);

        return $this->summarizeGatherings($gatherings);
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\AttendanceRecord> $records
     * @return array<int, array{label: string, present: int, absent: int, excused: int}>
     */
    private function buildWeeklyTrend(Collection $records, Carbon $windowStart): array
    {
        $buckets = [];
        $periodStart = $windowStart->copy();
        for ($i = 0; $i < 8; $i++) {
            $weekStart = $periodStart->copy()->addWeeks($i);
            $key = $weekStart->format('o-\WW');
            $buckets[$key] = [
                'label' => $weekStart->format('M j'),
                'present' => 0,
                'absent' => 0,
                'excused' => 0,
            ];
        }

        foreach ($records as $record) {
            $timestamp = $record->checked_in_at ?? $record->created_at ?? null;
            if (! $timestamp instanceof Carbon) {
                continue;
            }

            $weekStart = $timestamp->copy()->startOfWeek();
            if ($weekStart->lt($windowStart)) {
                $weekStart = $windowStart->copy();
            }

            $key = $weekStart->format('o-\WW');

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'label' => $weekStart->format('M j'),
                    'present' => 0,
                    'absent' => 0,
                    'excused' => 0,
                ];
            }

            $status = $record->status ?? 'unknown';
            if ($status === 'present') {
                $buckets[$key]['present']++;
            } elseif ($status === 'absent') {
                $buckets[$key]['absent']++;
            } elseif ($status === 'excused') {
                $buckets[$key]['excused']++;
            }
        }

        return array_values($buckets);
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\Gathering> $gatherings
     * @return array<int, array{id:int,uuid:string,name:string,service:?string,starts_at:?string,present:int,absent:int,excused:int,attendance_rate:float}>
     */
    private function summarizeGatherings(Collection $gatherings): array
    {
        return $gatherings
            ->map(function (Gathering $gathering) {
                $records = $gathering->attendanceRecords;
                $present = $records->where('status', 'present')->count();
                $absent = $records->where('status', 'absent')->count();
                $excused = $records->where('status', 'excused')->count();
                $total = $records->count();

                return [
                    'id' => $gathering->id,
                    'uuid' => $gathering->uuid,
                    'name' => $gathering->name,
                    'service' => $gathering->service?->name,
                    'starts_at' => optional($gathering->starts_at)->toIso8601String(),
                    'present' => $present,
                    'absent' => $absent,
                    'excused' => $excused,
                    'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0.0,
                ];
            })
            ->all();
    }

    private function buildFollowupCandidates(int $tenantId, Carbon $windowStart): array
    {
        $attendance = AttendanceRecord::query()
            ->select(['member_id', 'status'])
            ->where('tenant_id', $tenantId)
            ->whereNotNull('member_id')
            ->where('created_at', '>=', $windowStart)
            ->get()
            ->groupBy('member_id')
            ->map(function (Collection $records) {
                $absent = $records->where('status', 'absent')->count();
                $present = $records->where('status', 'present')->count();

                return [
                    'absent' => $absent,
                    'present' => $present,
                    'total' => $records->count(),
                ];
            })
            ->filter(fn ($data) => $data['absent'] >= 2)
            ->sortByDesc(fn ($data) => $data['absent'])
            ->take(5);

        if ($attendance->isEmpty()) {
            return [];
        }

        $members = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $attendance->keys())
            ->get()
            ->keyBy('id');

        return $attendance
            ->map(function (array $data, int $memberId) use ($members) {
                /** @var \App\Models\Member|null $member */
                $member = $members->get($memberId);

                return [
                    'member_id' => $memberId,
                    'member_name' => $member ? trim($member->first_name . ' ' . $member->last_name) : 'Unknown member',
                    'absent' => $data['absent'],
                    'present' => $data['present'],
                    'total' => $data['total'],
                ];
            })
            ->values()
            ->all();
    }

    private function fetchGatherings(int $tenantId, Carbon $windowStart): Collection
    {
        return Gathering::query()
            ->with(['service:id,name', 'attendanceRecords' => function ($query): void {
                $query->select([
                    'id',
                    'tenant_id',
                    'gathering_id',
                    'member_id',
                    'status',
                    'checked_in_at',
                    'created_at',
                ]);
            }])
            ->where('tenant_id', $tenantId)
            ->whereDate('starts_at', '>=', $windowStart->toDateString())
            ->orderBy('starts_at', 'desc')
            ->get();
    }
}
