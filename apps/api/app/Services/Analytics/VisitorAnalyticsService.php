<?php

namespace App\Services\Analytics;

use App\Models\Member;
use App\Models\VisitorFollowup;
use App\Models\VisitorFollowupLog;
use App\Models\VisitorWorkflow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VisitorAnalyticsService
{
    public function metrics(int $tenantId): array
    {
        $visitorMembers = Member::query()
            ->where('tenant_id', $tenantId)
            ->where('membership_status', 'visitor');

        $totalVisitors = (clone $visitorMembers)->count();

        $convertedVisitors = Member::query()
            ->where('tenant_id', $tenantId)
            ->where('membership_status', '!=', 'visitor')
            ->whereNotNull('joined_at')
            ->count();

        $activeFollowups = VisitorFollowup::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        $completedLast30Days = VisitorFollowup::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('completed_at', '>=', Carbon::now()->subDays(30))
            ->count();

        $stageBreakdown = VisitorFollowup::query()
            ->selectRaw('status, COUNT(*) as total')
            ->where('tenant_id', $tenantId)
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'total' => (int) $row->total,
            ]);

        $workflowPerformance = VisitorFollowup::query()
            ->selectRaw('workflow_id, status, COUNT(*) as total')
            ->where('tenant_id', $tenantId)
            ->groupBy('workflow_id', 'status')
            ->get()
            ->groupBy('workflow_id')
            ->map(function (Collection $rows, $workflowId) use ($tenantId) {
                /** @var VisitorWorkflow|null $workflow */
                $workflow = VisitorWorkflow::query()
                    ->where('tenant_id', $tenantId)
                    ->find($workflowId);

                $totals = [
                    'pending' => 0,
                    'in_progress' => 0,
                    'completed' => 0,
                    'halted' => 0,
                ];

                foreach ($rows as $row) {
                    $totals[$row->status] = (int) $row->total;
                }

                return [
                    'workflow_id' => (int) $workflowId,
                    'workflow_name' => $workflow?->name ?? 'Unknown workflow',
                    'pending' => $totals['pending'],
                    'in_progress' => $totals['in_progress'],
                    'completed' => $totals['completed'],
                    'halted' => $totals['halted'],
                ];
            })
            ->values();

        $recentActivity = VisitorFollowupLog::query()
            ->with(['followup.member', 'step'])
            ->whereHas('followup', fn ($query) => $query->where('tenant_id', $tenantId))
            ->latest('run_at')
            ->limit(15)
            ->get()
            ->map(function (VisitorFollowupLog $log) {
                return [
                    'id' => $log->id,
                    'status' => $log->status,
                    'channel' => $log->channel,
                    'notes' => $log->notes,
                    'run_at' => optional($log->run_at)->toIso8601String(),
                    'step' => $log->step?->name,
                    'member' => $log->followup?->member ? [
                        'id' => $log->followup->member->id,
                        'first_name' => $log->followup->member->first_name,
                        'last_name' => $log->followup->member->last_name,
                    ] : null,
                ];
            });

        $conversionRate = $totalVisitors > 0
            ? round(($convertedVisitors / $totalVisitors) * 100, 1)
            : 0.0;

        return [
            'stats' => [
                'total_visitors' => $totalVisitors,
                'converted_visitors' => $convertedVisitors,
                'active_followups' => $activeFollowups,
                'completed_last_30_days' => $completedLast30Days,
                'conversion_rate' => $conversionRate,
            ],
            'breakdown' => [
                'followup_statuses' => $stageBreakdown,
                'workflows' => $workflowPerformance,
            ],
            'recent_activity' => $recentActivity,
        ];
    }
}
