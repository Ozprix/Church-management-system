<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\StoreNotificationRuleRequest;
use App\Http\Requests\Notification\UpdateNotificationRuleRequest;
use App\Http\Resources\NotificationRuleResource;
use App\Models\NotificationRule;
use App\Services\NotificationAutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationRuleController extends Controller
{
    public function __construct(private readonly NotificationAutomationService $service)
    {
        $this->middleware('feature:notifications_automation');
        $this->middleware('can:notifications.rules_manage')->only(['store', 'update', 'destroy']);
        $this->middleware('can:notifications.rules_run')->only(['run', 'index', 'show']);
    }

    public function index(Request $request): JsonResponse
    {
        $rules = NotificationRule::query()
            ->with('template')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20))
            ->appends($request->query());

        return NotificationRuleResource::collection($rules)->response();
    }

    public function store(StoreNotificationRuleRequest $request): JsonResponse
    {
        $rule = $this->service->createRule($request->validated());

        return NotificationRuleResource::make($rule->load('template'))->response()->setStatusCode(201);
    }

    public function show(NotificationRule $notificationRule): JsonResponse
    {
        return NotificationRuleResource::make($notificationRule->load(['template', 'runs']))->response();
    }

    public function update(UpdateNotificationRuleRequest $request, NotificationRule $notificationRule): JsonResponse
    {
        $rule = $this->service->updateRule($notificationRule, $request->validated());

        return NotificationRuleResource::make($rule->load('template'))->response();
    }

    public function destroy(NotificationRule $notificationRule): JsonResponse
    {
        $notificationRule->delete();

        return response()->json([], 204);
    }

    public function run(NotificationRule $notificationRule): JsonResponse
    {
        $run = $this->service->runRule($notificationRule);

        return response()->json([
            'data' => $run,
        ]);
    }
}
