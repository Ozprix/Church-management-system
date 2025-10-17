<?php

namespace App\Services\Notifications;

use App\Models\Notification;

interface NotificationChannelDriver
{
    public function send(Notification $notification): NotificationChannelResponse;
}
