<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsChannelDriver implements NotificationChannelDriver
{
    public function send(Notification $notification): NotificationChannelResponse
    {
        $config = config('services.twilio');
        $mock = (bool) ($config['mock'] ?? true);

        if ($mock || empty($config['sid']) || empty($config['token']) || empty($config['from'])) {
            $messageId = Str::uuid()->toString();
            Log::info('Mock Twilio SMS send', [
                'recipient' => $notification->recipient,
                'body' => $notification->body,
                'message_id' => $messageId,
            ]);

            return NotificationChannelResponse::success('twilio', $messageId);
        }

        try {
            $sid = $config['sid'];
            $token = $config['token'];
            $from = $config['from'];

            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To' => $notification->recipient,
                    'Body' => $notification->body ?? '',
                ]);

            if ($response->successful()) {
                $messageId = $response->json('sid') ?? Str::uuid()->toString();
                return NotificationChannelResponse::success('twilio', $messageId);
            }

            $error = $response->json('message') ?? $response->body();
            return NotificationChannelResponse::failure('twilio', $error ?? 'Twilio request failed.');
        } catch (\Throwable $exception) {
            Log::error('Twilio SMS failed', [
                'recipient' => $notification->recipient,
                'error' => $exception->getMessage(),
            ]);
            return NotificationChannelResponse::failure('twilio', $exception->getMessage());
        }
    }
}
