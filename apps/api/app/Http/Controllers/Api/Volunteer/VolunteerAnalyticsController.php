<?php

namespace App\Http\Controllers\Api\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerRole;
use App\Models\VolunteerSignup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VolunteerAnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('feature:volunteers');
        $this->middleware('can:volunteers.view');
    }

    public function summary(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $roleStats = VolunteerRole::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('active_assignment_count')
            ->take(10)
            ->get(['id', 'name', 'active_assignment_count', 'pending_signup_count']);

        $upcomingAssignments = VolunteerAssignment::query()
            ->where('tenant_id', $tenant->id)
            ->where('starts_at', '>=', Carbon::now())
            ->select(DB::raw('DATE(starts_at) as day'), DB::raw('COUNT(*) as count'))
            ->groupBy('day')
            ->orderBy('day')
            ->take(7)
            ->get();

        $signupStats = VolunteerSignup::query()
            ->where('tenant_id', $tenant->id)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $openAssignments = VolunteerAssignment::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'scheduled')
            ->count();

        return response()->json([
            'data' => [
                'top_roles' => $roleStats,
                'upcoming_assignments' => $upcomingAssignments,
                'signups_by_status' => $signupStats,
                'open_assignments' => $openAssignments,
            ],
        ]);
    }
}
