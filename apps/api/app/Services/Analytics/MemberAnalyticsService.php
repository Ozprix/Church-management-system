<?php

namespace App\Services\Analytics;

use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class MemberAnalyticsService
{
    /**
     * @return array{
     *     status?: string|null,
     *     stage?: string|null,
     *     joined_from?: ?Carbon,
     *     joined_to?: ?Carbon,
     *     with_family?: bool|null
     * }
     */
    public function parseFilters(array $query): array
    {
        $status = $query['status'] ?? null;
        $stage = $query['stage'] ?? null;

        $joinedFrom = null;
        if (! empty($query['joined_from'])) {
            $joinedFrom = Carbon::make($query['joined_from']);
        }

        $joinedTo = null;
        if (! empty($query['joined_to'])) {
            $joinedTo = Carbon::make($query['joined_to'])->endOfDay();
        }

        $withFamily = null;
        if (array_key_exists('with_family', $query)) {
            $withFamily = filter_var($query['with_family'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return [
            'status' => $status !== '' ? $status : null,
            'stage' => $stage !== '' ? $stage : null,
            'joined_from' => $joinedFrom,
            'joined_to' => $joinedTo,
            'with_family' => $withFamily,
        ];
    }

    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['status'])) {
            $query->where('membership_status', $filters['status']);
        }

        if (! empty($filters['stage'])) {
            $query->where('membership_stage', $filters['stage']);
        }

        if ($filters['joined_from'] instanceof Carbon) {
            $query->where('created_at', '>=', $filters['joined_from']);
        }

        if ($filters['joined_to'] instanceof Carbon) {
            $query->where('created_at', '<=', $filters['joined_to']);
        }

        if (array_key_exists('with_family', $filters) && $filters['with_family'] !== null) {
            if ($filters['with_family'] === true) {
                $query->whereHas('families');
            } else {
                $query->whereDoesntHave('families');
            }
        }

        return $query;
    }

    public function buildMetrics(array $filters): array
    {
        $baseQuery = $this->applyFilters(Member::query(), $filters);

        $totalMembers = (clone $baseQuery)->count();

        $byStatus = (clone $baseQuery)
            ->select('membership_status')
            ->selectRaw('count(*) as total')
            ->groupBy('membership_status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->membership_status ?? 'unknown',
                'total' => (int) $row->total,
            ])
            ->values();

        $byStage = (clone $baseQuery)
            ->select('membership_stage')
            ->selectRaw('count(*) as total')
            ->whereNotNull('membership_stage')
            ->groupBy('membership_stage')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'stage' => $row->membership_stage ?? 'unspecified',
                'total' => (int) $row->total,
            ])
            ->values();

        $trendStart = now()->startOfMonth()->subMonths(5);
        $trendBuckets = [];
        for ($i = 0; $i < 6; $i++) {
            $month = (clone $trendStart)->addMonths($i);
            $trendBuckets[$month->format('Y-m')] = [
                'label' => $month->format('M Y'),
                'total' => 0,
            ];
        }

        $trendQuery = $this->applyFilters(Member::query()->where('created_at', '>=', $trendStart), $filters);
        $trendQuery
            ->select(['created_at'])
            ->orderBy('created_at')
            ->chunk(200, function ($chunk) use (&$trendBuckets, $trendStart) {
                foreach ($chunk as $member) {
                    if (! $member->created_at) {
                        continue;
                    }
                    $bucket = $member->created_at->copy()->startOfMonth();
                    if ($bucket->lt($trendStart)) {
                        $bucket = $trendStart->copy();
                    }
                    $key = $bucket->format('Y-m');
                    if (! isset($trendBuckets[$key])) {
                        $trendBuckets[$key] = [
                            'label' => $bucket->format('M Y'),
                            'total' => 0,
                        ];
                    }
                    $trendBuckets[$key]['total']++;
                }
            });

        $withFamilyCount = (clone $baseQuery)->whereHas('families')->count();
        $staleSince = now()->copy()->subMonths(6);
        $staleProfiles = (clone $baseQuery)->where('updated_at', '<', $staleSince)->count();

        $recentMembers = $this->applyFilters(Member::query(), $filters)
            ->with('families')
            ->latest()
            ->take(5)
            ->get()
            ->map(function (Member $member) {
                return [
                    'id' => $member->id,
                    'uuid' => $member->uuid,
                    'name' => trim($member->first_name . ' ' . $member->last_name),
                    'status' => $member->membership_status,
                    'stage' => $member->membership_stage,
                    'joined_at' => optional($member->created_at)->toIso8601String(),
                ];
            });

        return [
            'totals' => [
                'members' => $totalMembers,
                'members_without_family' => max($totalMembers - $withFamilyCount, 0),
                'stale_profiles' => $staleProfiles,
            ],
            'by_status' => $byStatus,
            'by_stage' => $byStage,
            'new_members_trend' => array_values($trendBuckets),
            'recent_members' => $recentMembers,
        ];
    }
}
