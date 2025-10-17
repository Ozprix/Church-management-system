<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailChannelDriver implements NotificationChannelDriver
{
    public function send(Notification $notification): NotificationChannelResponse
    {
        $config = config('services.mailgun');
        $mock = (bool) ($config['mock'] ?? true);

        if ($mock) {
            $messageId = Str::uuid()->toString();
            Log::info('Mock Mailgun email send', [
                'recipient' => $notification->recipient,
                'subject' => $notification->subject,
                'message_id' => $messageId,
            ]);

            return NotificationChannelResponse::success('mailgun', $messageId);
        }

        try {
            $fromAddress = $config['from'] ?? config('mail.from.address');
            $fromName = config('mail.from.name');

            Mail::raw($notification->body ?? '', function ($message) use ($notification, $fromAddress, $fromName) {
                if ($fromAddress) {
                    $message->from($fromAddress, $fromName);
                }
                $message->to($notification->recipient);
                if ($notification->subject) {
                    $message->subject($notification->subject);
                }
            });

            $messageId = Str::uuid()->toString();
            return NotificationChannelResponse::success('mail', $messageId);
        } catch (\Throwable $exception) {
            Log::error('Email dispatch failed', [
                'recipient' => $notification->recipient,
                'error' => $exception->getMessage(),
            ]);

            return NotificationChannelResponse::failure('mail', $exception->getMessage());
        }
    }
}
