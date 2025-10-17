<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\BulkDeleteMembersRequest;
use App\Http\Requests\Member\BulkImportMembersRequest;
use App\Http\Requests\Member\StoreMemberRequest;
use App\Http\Requests\Member\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Http\Resources\MemberSummaryResource;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(private readonly MemberService $memberService)
    {
        $this->middleware('feature:members');
        $this->middleware('can:members.view')->only(['index', 'show']);
        $this->middleware('can:members.manage')->only([
            'store',
            'bulkImport',
            'bulkDelete',
            'update',
            'destroy',
            'restore',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Member::query()->with(['contacts', 'families']);

        if ($status = $request->query('status')) {
            $query->where('membership_status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('preferred_name', 'like', "%{$search}%")
                    ->orWhere('membership_stage', 'like', "%{$search}%");
            });
        }

        $sort = $request->query('sort');
        $direction = strtolower($request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortableColumns = [
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'membership_status' => 'membership_status',
            'membership_stage' => 'membership_stage',
            'created_at' => 'created_at',
        ];

        if ($sort && array_key_exists($sort, $sortableColumns)) {
            $query->orderBy($sortableColumns[$sort], $direction);
        } else {
            $query->orderBy('last_name')->orderBy('first_name');
        }

        $members = $query
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return MemberSummaryResource::collection($members)->response();
    }

    public function store(StoreMemberRequest $request): JsonResponse
    {
        $member = $this->memberService->create($request->validated());

        return MemberResource::make($member)->response()->setStatusCode(201);
    }

    public function bulkImport(BulkImportMembersRequest $request): JsonResponse
    {
        $members = $this->memberService->bulkImport($request->validated('members'));

        return MemberResource::collection($members)->response()->setStatusCode(201);
    }

    public function show(Member $member): JsonResponse
    {
        $member->load(['contacts', 'families', 'customValues.field']);

        return MemberResource::make($member)->response();
    }

    public function bulkDelete(BulkDeleteMembersRequest $request): JsonResponse
    {
        $deletedCount = $this->memberService->bulkDelete($request->validated('member_ids'));

        return response()->json([
            'deleted' => $deletedCount,
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member): JsonResponse
    {
        $member = $this->memberService->update($member, $request->validated());

        return MemberResource::make($member)->response();
    }

    public function restore(string $memberUuid): JsonResponse
    {
        $member = Member::withTrashed()->where('uuid', $memberUuid)->firstOrFail();

        $member = $this->memberService->restore($member);

        return MemberResource::make($member)->response();
    }

    public function destroy(Member $member): JsonResponse
    {
        $this->memberService->delete($member);

        return response()->json([], 204);
    }
}
