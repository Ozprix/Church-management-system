<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Visitor\StoreVisitorWorkflowStepRequest;
use App\Http\Requests\Visitor\UpdateVisitorWorkflowStepRequest;
use App\Http\Resources\VisitorWorkflowStepResource;
use App\Models\VisitorWorkflow;
use App\Models\VisitorWorkflowStep;
use Illuminate\Http\JsonResponse;

class VisitorWorkflowStepController extends Controller
{
    public function __construct()
    {
        $this->middleware('feature:visitors');
        $this->middleware('can:visitors.manage_workflows');
    }

    public function store(StoreVisitorWorkflowStepRequest $request, VisitorWorkflow $visitorWorkflow): JsonResponse
    {
        $step = $visitorWorkflow->steps()->create($request->validated());

        return VisitorWorkflowStepResource::make($step)->response()->setStatusCode(201);
    }

    public function update(UpdateVisitorWorkflowStepRequest $request, VisitorWorkflow $visitorWorkflow, VisitorWorkflowStep $visitorWorkflowStep): VisitorWorkflowStepResource
    {
        abort_unless($visitorWorkflowStep->workflow_id === $visitorWorkflow->id, 404);

        $visitorWorkflowStep->fill($request->validated());
        $visitorWorkflowStep->save();

        return VisitorWorkflowStepResource::make($visitorWorkflowStep);
    }

    public function destroy(VisitorWorkflow $visitorWorkflow, VisitorWorkflowStep $visitorWorkflowStep): JsonResponse
    {
        abort_unless($visitorWorkflowStep->workflow_id === $visitorWorkflow->id, 404);

        $visitorWorkflowStep->delete();

        return response()->json([], 204);
    }
}
