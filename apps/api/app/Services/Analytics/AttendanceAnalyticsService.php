<?php

namespace App\Services\Analytics;

use App\Models\AttendanceRecord;
use App\Models\Gathering;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AttendanceAnalyticsService
{
    public const ALLOWED_STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled'];

    public function metrics(int $tenantId, array $filtersInput = []): array
    {
        $filters = $this->parseFilters($filtersInput);

        $gatherings = $this->fetchGatherings($tenantId, $filters);
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
        $trend = $this->buildWeeklyTrend($attendanceRecords, $filters['from'], $filters['to']);

        $topGatherings = $summariesCollection
            ->sortByDesc('present')
            ->values()
            ->take(5)
            ->map(fn (array $row) => $row)
            ->all();

        $followupCandidates = $this->buildFollowupCandidates($tenantId, $filters);
        $serviceBreakdown = $this->buildServiceBreakdown($summariesCollection);
        $memberSegments = $this->buildMembershipStatusSegments($attendanceRecords);
        $departmentSegments = $this->buildDepartmentSegments($attendanceRecords);
        $ageBands = $this->buildAgeBandSegments($attendanceRecords);

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
            'service_breakdown' => $serviceBreakdown,
            'member_segments' => $memberSegments,
            'department_segments' => $departmentSegments,
            'age_bands' => $ageBands,
        ];
    }

    public function gatheringSummaries(int $tenantId, array $filtersInput = []): array
    {
        $filters = $this->parseFilters($filtersInput);
        $gatherings = $this->fetchGatherings($tenantId, $filters);

        return $this->summarizeGatherings($gatherings);
    }

    public function parseFilters(array $input): array
    {
        $now = Carbon::now();
        $defaultFrom = $now->copy()->startOfWeek()->subWeeks(7);
        $defaultTo = $now->copy();

        $from = $this->parseDate(Arr::get($input, 'from'))?->startOfDay() ?? $defaultFrom;
        $to = $this->parseDate(Arr::get($input, 'to'))?->endOfDay() ?? $defaultTo;

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $serviceId = Arr::has($input, 'service_id') && Arr::get($input, 'service_id') !== ''
            ? (int) Arr::get($input, 'service_id')
            : null;

        $status = Arr::has($input, 'status') && in_array(Arr::get($input, 'status'), self::ALLOWED_STATUSES, true)
            ? (string) Arr::get($input, 'status')
            : null;

        return [
            'from' => $from,
            'to' => $to,
            'service_id' => $serviceId,
            'status' => $status,
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\AttendanceRecord> $records
     * @return array<int, array{label: string, present: int, absent: int, excused: int}>
     */
    private function buildWeeklyTrend(Collection $records, Carbon $windowStart, Carbon $windowEnd): array
    {
        $buckets = [];
        $periodStart = $windowStart->copy()->startOfWeek();
        $periodEnd = $windowEnd->copy()->endOfWeek();

        $weekPointer = $periodStart->copy();
        while ($weekPointer->lte($periodEnd)) {
            $key = $weekPointer->format('o-\\WW');
            $buckets[$key] = [
                'label' => $weekPointer->format('M j'),
                'present' => 0,
                'absent' => 0,
                'excused' => 0,
            ];
            $weekPointer->addWeek();
        }

        foreach ($records as $record) {
            $timestamp = $record->checked_in_at ?? $record->created_at ?? null;
            if (! $timestamp instanceof Carbon) {
                continue;
            }

            if ($timestamp->lt($windowStart) || $timestamp->gt($windowEnd)) {
                continue;
            }

            $weekStart = $timestamp->copy()->startOfWeek();
            $key = $weekStart->format('o-\\WW');

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
                    'service_id' => $gathering->service_id,
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

    private function buildFollowupCandidates(int $tenantId, array $filters): array
    {
        $attendance = AttendanceRecord::query()
            ->select(['member_id', 'status'])
            ->where('tenant_id', $tenantId)
            ->whereNotNull('member_id')
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', $to))
            ->when($filters['service_id'] ?? null, function ($query, $serviceId) {
                $query->whereHas('gathering', fn ($gatheringQuery) => $gatheringQuery->where('service_id', $serviceId));
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->whereHas('gathering', fn ($gatheringQuery) => $gatheringQuery->where('status', $status));
            })
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

    private function buildServiceBreakdown(Collection $gatherings): array
    {
        if ($gatherings->isEmpty()) {
            return [];
        }

        return $gatherings
            ->groupBy(fn (array $row) => $row['service_id'] ?? 0)
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'service_id' => $first['service_id'],
                    'service_name' => $first['service'] ?? 'Unassigned',
                    'gatherings_tracked' => $rows->count(),
                    'present' => (int) $rows->sum('present'),
                    'absent' => (int) $rows->sum('absent'),
                    'excused' => (int) $rows->sum('excused'),
                ];
            })
            ->sortByDesc('present')
            ->values()
            ->all();
    }

    private function buildMembershipStatusSegments(Collection $records): array
    {
        if ($records->isEmpty()) {
            return [];
        }

        $segments = [];

        foreach ($records as $record) {
            $member = $record->member;
            if (! $member) {
                continue;
            }

            $key = $member->membership_status ?: 'uncategorized';

            if (! isset($segments[$key])) {
                $segments[$key] = [
                    'membership_status' => $key,
                    'label' => $this->formatMembershipStatusLabel($key),
                    'member_ids' => [],
                    'present' => 0,
                    'absent' => 0,
                    'excused' => 0,
                ];
            }

            $segments[$key]['member_ids'][$member->id] = true;

            $status = $record->status ?? 'unknown';
            if ($status === 'present') {
                $segments[$key]['present']++;
            } elseif ($status === 'absent') {
                $segments[$key]['absent']++;
            } elseif ($status === 'excused') {
                $segments[$key]['excused']++;
            }
        }

        return collect($segments)
            ->map(function (array $segment) {
                $total = $segment['present'] + $segment['absent'] + $segment['excused'];

                return [
                    'membership_status' => $segment['membership_status'],
                    'label' => $segment['label'],
                    'members' => count($segment['member_ids']),
                    'present' => $segment['present'],
                    'absent' => $segment['absent'],
                    'excused' => $segment['excused'],
                    'total_records' => $total,
                ];
            })
            ->sortByDesc('present')
            ->values()
            ->all();
    }

    private function buildDepartmentSegments(Collection $records): array
    {
        if ($records->isEmpty()) {
            return [];
        }

        $segments = [];

        foreach ($records as $record) {
            $member = $record->member;
            if (! $member) {
                continue;
            }

            $key = $member->membership_stage ?: 'unspecified';

            if (! isset($segments[$key])) {
                $segments[$key] = [
                    'department' => $key,
                    'label' => $this->formatDepartmentLabel($key),
                    'member_ids' => [],
                    'present' => 0,
                    'absent' => 0,
                    'excused' => 0,
                ];
            }

            $segments[$key]['member_ids'][$member->id] = true;

            $status = $record->status ?? 'unknown';
            if ($status === 'present') {
                $segments[$key]['present']++;
            } elseif ($status === 'absent') {
                $segments[$key]['absent']++;
            } elseif ($status === 'excused') {
                $segments[$key]['excused']++;
            }
        }

        return collect($segments)
            ->map(function (array $segment) {
                $total = $segment['present'] + $segment['absent'] + $segment['excused'];

                return [
                    'department' => $segment['department'],
                    'label' => $segment['label'],
                    'members' => count($segment['member_ids']),
                    'present' => $segment['present'],
                    'absent' => $segment['absent'],
                    'excused' => $segment['excused'],
                    'total_records' => $total,
                ];
            })
            ->sortByDesc('present')
            ->values()
            ->all();
    }

    private function buildAgeBandSegments(Collection $records): array
    {
        if ($records->isEmpty()) {
            return [];
        }

        $bands = [
            'child' => ['label' => 'Kids (0-11)', 'min' => 0, 'max' => 11],
            'youth' => ['label' => 'Youth (12-17)', 'min' => 12, 'max' => 17],
            'young_adult' => ['label' => 'Young Adults (18-25)', 'min' => 18, 'max' => 25],
            'adult' => ['label' => 'Adults (26-39)', 'min' => 26, 'max' => 39],
            'prime' => ['label' => 'Prime (40-59)', 'min' => 40, 'max' => 59],
            'senior' => ['label' => 'Seniors (60+)', 'min' => 60, 'max' => null],
            'unknown' => ['label' => 'Unknown age', 'min' => null, 'max' => null],
        ];

        $segments = [];

        foreach ($records as $record) {
            $member = $record->member;
            if (! $member) {
                continue;
            }

            $bandKey = $this->determineAgeBandKey($member->dob, $bands);

            if (! isset($segments[$bandKey])) {
                $segments[$bandKey] = [
                    'band' => $bandKey,
                    'label' => $bands[$bandKey]['label'],
                    'member_ids' => [],
                    'present' => 0,
                    'absent' => 0,
                    'excused' => 0,
                ];
            }

            $segments[$bandKey]['member_ids'][$member->id] = true;

            $status = $record->status ?? 'unknown';
            if ($status === 'present') {
                $segments[$bandKey]['present']++;
            } elseif ($status === 'absent') {
                $segments[$bandKey]['absent']++;
            } elseif ($status === 'excused') {
                $segments[$bandKey]['excused']++;
            }
        }

        return collect($segments)
            ->map(function (array $segment) {
                $total = $segment['present'] + $segment['absent'] + $segment['excused'];

                return [
                    'band' => $segment['band'],
                    'label' => $segment['label'],
                    'members' => count($segment['member_ids']),
                    'present' => $segment['present'],
                    'absent' => $segment['absent'],
                    'excused' => $segment['excused'],
                    'total_records' => $total,
                ];
            })
            ->sortByDesc('present')
            ->values()
            ->all();
    }

    private function determineAgeBandKey(?Carbon $dob, array $bands): string
    {
        if (! $dob instanceof Carbon) {
            return 'unknown';
        }

        $age = $dob->diffInYears(Carbon::now());

        foreach ($bands as $key => $band) {
            if ($key === 'unknown') {
                continue;
            }

            $min = $band['min'];
            $max = $band['max'];

            if (($min === null || $age >= $min) && ($max === null || $age <= $max)) {
                return $key;
            }
        }

        return 'unknown';
    }

    private function formatMembershipStatusLabel(?string $status): string
    {
        if (! $status || $status === 'uncategorized') {
            return 'Uncategorized';
        }

        return (string) Str::of($status)->replace('_', ' ')->title();
    }

    private function formatDepartmentLabel(?string $stage): string
    {
        if (! $stage || $stage === 'unspecified') {
            return 'Unassigned department';
        }

        return (string) Str::of($stage)->replace('_', ' ')->title();
    }

    private function fetchGatherings(int $tenantId, array $filters): Collection
    {
        $query = Gathering::query()
            ->with([
                'service:id,name',
                'attendanceRecords' => function ($query): void {
                    $query->select([
                        'id',
                        'tenant_id',
                        'gathering_id',
                        'member_id',
                        'status',
                        'checked_in_at',
                        'created_at',
                    ])->with('member:id,tenant_id,membership_status,membership_stage,dob');
                },
            ])
            ->where('tenant_id', $tenantId)
            ->when($filters['service_id'] ?? null, fn ($q, $serviceId) => $q->where('service_id', $serviceId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('starts_at');

        return $query
            ->whereBetween('starts_at', [$filters['from'], $filters['to']])
            ->get();
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
