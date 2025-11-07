<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VisitorFollowupLogResource;
use App\Models\VisitorFollowup;
use App\Models\VisitorFollowupLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorFollowupLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('feature:visitors');
        $this->middleware('can:visitors.manage_followups');
    }

    public function index(Request $request, VisitorFollowup $visitorFollowup): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($tenant && $tenant->id !== $visitorFollowup->tenant_id) {
            abort(404);
        }

        $logs = VisitorFollowupLog::query()
            ->with('step')
            ->where('followup_id', $visitorFollowup->id)
            ->orderByDesc('run_at')
            ->paginate($request->integer('per_page', 20))
            ->appends($request->query());

        return VisitorFollowupLogResource::collection($logs)->response();
    }
}
