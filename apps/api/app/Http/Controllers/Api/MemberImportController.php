<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\StoreMemberImportRequest;
use App\Http\Resources\MemberImportResource;
use App\Jobs\ProcessMemberImport;
use App\Models\MemberImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemberImportController extends Controller
{
    public function __construct()
    {
        $this->middleware('feature:members');
        $this->middleware('can:members.manage');
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant context is required.',
            ], 400);
        }

        $imports = MemberImport::query()
            ->where('tenant_id', $tenant?->id)
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return MemberImportResource::collection($imports)->response();
    }

    public function store(StoreMemberImportRequest $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant context is required.',
            ], 400);
        }
        $file = $request->file('file');

        $storedPath = $file->storeAs(
            "member-imports/{$tenant->id}",
            uniqid('member-import_') . '.' . $file->getClientOriginalExtension()
        );

        $import = MemberImport::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => optional($request->user())->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => MemberImport::STATUS_PENDING,
        ]);

        ProcessMemberImport::dispatch($import);

        return MemberImportResource::make($import)->response()->setStatusCode(202);
    }

    public function show(Request $request, MemberImport $memberImport): JsonResponse
    {
        return MemberImportResource::make($memberImport)->response();
    }
}
