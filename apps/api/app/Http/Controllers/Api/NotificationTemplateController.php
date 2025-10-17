<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationTemplate\StoreNotificationTemplateRequest;
use App\Http\Requests\NotificationTemplate\UpdateNotificationTemplateRequest;
use App\Http\Resources\NotificationTemplateResource;
use App\Models\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('feature:notifications');
        $this->middleware('can:notifications.view')->only(['index', 'show']);
        $this->middleware('can:notifications.manage_templates')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $templates = NotificationTemplate::query()
            ->when($request->query('channel'), fn ($query, $channel) => $query->where('channel', $channel))
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return NotificationTemplateResource::collection($templates)->response();
    }

    public function store(StoreNotificationTemplateRequest $request): JsonResponse
    {
        $template = NotificationTemplate::create($request->validated());

        return NotificationTemplateResource::make($template)->response()->setStatusCode(201);
    }

    public function show(NotificationTemplate $notificationTemplate): JsonResponse
    {
        return NotificationTemplateResource::make($notificationTemplate)->response();
    }

    public function update(UpdateNotificationTemplateRequest $request, NotificationTemplate $notificationTemplate): JsonResponse
    {
        $notificationTemplate->fill($request->validated());
        $notificationTemplate->save();

        return NotificationTemplateResource::make($notificationTemplate)->response();
    }

    public function destroy(NotificationTemplate $notificationTemplate): JsonResponse
    {
        $notificationTemplate->delete();

        return response()->json([], 204);
    }
}
