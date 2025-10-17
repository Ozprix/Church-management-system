<?php

namespace App\Jobs;

use App\Jobs\Concerns\DispatchesForTenant;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SendNotificationJob implements ShouldQueue
{
    use DispatchesForTenant;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $notificationId)
    {
    }

    public function handle(NotificationService $notificationService): void
    {
        $notification = Notification::query()->find($this->notificationId);

        if (! $notification) {
            return;
        }

        if ($notification->status === 'sent') {
            return;
        }

        if ($notification->scheduled_for instanceof Carbon && $notification->scheduled_for->isFuture()) {
            $this->release($notification->scheduled_for->diffInSeconds(now()));

            return;
        }

        $notificationService->send($notification);
    }
}
