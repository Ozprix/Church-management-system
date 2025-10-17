<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Visitor\StoreVisitorWorkflowRequest;
use App\Http\Requests\Visitor\UpdateVisitorWorkflowRequest;
use App\Http\Resources\VisitorWorkflowResource;
use App\Models\VisitorWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorWorkflowController extends Controller
{
    public function __construct()
    {
        $this->middleware('feature:visitors');
        $this->middleware('can:visitors.manage_workflows');
    }

    public function index(Request $request): JsonResponse
    {
        $workflows = VisitorWorkflow::query()
            ->with('steps')
            ->when($request->boolean('active'), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20))
            ->appends($request->query());

        return VisitorWorkflowResource::collection($workflows)->response();
    }

    public function store(StoreVisitorWorkflowRequest $request): JsonResponse
    {
        $workflow = VisitorWorkflow::create([
            ...$request->validated(),
            'tenant_id' => optional($request->attributes->get('tenant'))->id ?? auth()->user()?->tenant_id,
        ]);

        return VisitorWorkflowResource::make($workflow)->response()->setStatusCode(201);
    }

    public function show(VisitorWorkflow $visitorWorkflow): VisitorWorkflowResource
    {
        return VisitorWorkflowResource::make($visitorWorkflow->load('steps'));
    }

    public function update(UpdateVisitorWorkflowRequest $request, VisitorWorkflow $visitorWorkflow): VisitorWorkflowResource
    {
        $visitorWorkflow->fill($request->validated());
        $visitorWorkflow->save();

        return VisitorWorkflowResource::make($visitorWorkflow->fresh('steps'));
    }

    public function destroy(VisitorWorkflow $visitorWorkflow): JsonResponse
    {
        if ($visitorWorkflow->followups()->exists()) {
            return response()->json([
                'message' => 'Cannot delete workflow with active followups.',
            ], 422);
        }

        $visitorWorkflow->delete();

        return response()->json([], 204);
    }
}
