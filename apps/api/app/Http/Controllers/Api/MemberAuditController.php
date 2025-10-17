<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberAuditController extends Controller
{
    public function __construct()
    {
        $this->middleware('feature:members');
        $this->middleware('can:members.view');
    }

    public function index(Request $request, Member $member): JsonResponse
    {
        $logs = AuditLog::query()
            ->where('tenant_id', $member->tenant_id)
            ->where(function ($query) use ($member) {
                $query->where('auditable_type', Member::class)
                    ->where('auditable_id', $member->id)
                    ->orWhere(function ($inner) use ($member) {
                        $inner->where('payload->member_id', $member->id);
                    });
            })
            ->latest('occurred_at')
            ->latest('created_at')
            ->with('user')
            ->paginate($request->integer('per_page', 25));

        return AuditLogResource::collection($logs)->response();
    }
}
