<?php

namespace App\Http\Controllers\Api\Membership;

use App\Http\Controllers\Controller;
use App\Models\MemberProcessRun;
use App\Models\MembershipProcess;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MembershipProcessReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('feature:membership_processes');
        $this->middleware('can:membership_processes.manage');
    }

    public function show(MembershipProcess $membershipProcess): JsonResponse
    {
        $runs = MemberProcessRun::query()->where('process_id', $membershipProcess->id);

        $total = (clone $runs)->count();

        $statusCounts = (clone $runs)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $stageCounts = MemberProcessRun::query()
            ->select('current_stage_id', DB::raw('COUNT(*) as count'))
            ->where('process_id', $membershipProcess->id)
            ->whereNotNull('current_stage_id')
            ->groupBy('current_stage_id')
            ->with('currentStage:id,name')
            ->get()
            ->map(fn ($row) => [
                'stage_id' => $row->current_stage_id,
                'stage_name' => $row->currentStage?->name,
                'count' => $row->count,
            ])->all();

        $completionTimes = MemberProcessRun::query()
            ->where('process_id', $membershipProcess->id)
            ->whereNotNull('completed_at')
            ->select(DB::raw('TIMESTAMPDIFF(HOUR, started_at, completed_at) as hours'))
            ->pluck('hours');

        $averageCompletionHours = $completionTimes->count() > 0
            ? round($completionTimes->avg(), 2)
            : null;

        return response()->json([
            'data' => [
                'total_runs' => $total,
                'status_counts' => $statusCounts,
                'stage_counts' => $stageCounts,
                'average_completion_hours' => $averageCompletionHours,
            ],
        ]);
    }
}
