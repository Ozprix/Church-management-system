<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\StoreNotificationRequest;
use App\Http\Requests\Notification\UpdateNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
        $this->middleware('feature:notifications');
        $this->middleware('can:notifications.view')->only(['index', 'show']);
        $this->middleware('can:notifications.manage')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::query()
            ->with(['template', 'member'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('channel'), fn ($query, $channel) => $query->where('channel', $channel))
            ->when($request->query('recipient'), fn ($query, $recipient) => $query->where('recipient', 'like', "%{$recipient}%"))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return NotificationResource::collection($notifications)->response();
    }

    public function store(StoreNotificationRequest $request): JsonResponse
    {
        $notification = $this->notificationService->queue($request->validated());

        return NotificationResource::make($notification->load(['template', 'member']))->response()->setStatusCode(201);
    }

    public function show(Notification $notification): JsonResponse
    {
        $notification->load(['template', 'member']);

        return NotificationResource::make($notification)->response();
    }

    public function update(UpdateNotificationRequest $request, Notification $notification): JsonResponse
    {
        $notification->fill($request->validated());
        $notification->save();

        if ($notification->status === 'queued') {
            SendNotificationJob::dispatch($notification->id);
        }

        return NotificationResource::make($notification->load(['template', 'member']))->response();
    }

    public function destroy(Notification $notification): JsonResponse
    {
        $notification->delete();

        return response()->json([], 204);
    }
}
