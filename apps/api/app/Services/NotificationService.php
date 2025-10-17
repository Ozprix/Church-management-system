<?php

namespace App\Services;

use App\Jobs\SendNotificationJob;
use App\Models\Member;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Services\Notifications\EmailChannelDriver;
use App\Services\Notifications\NotificationChannelDriver;
use App\Services\Notifications\NotificationChannelResponse;
use App\Services\Notifications\SmsChannelDriver;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * @var array<string, NotificationChannelDriver>
     */
    private array $drivers;

    public function __construct(
        SmsChannelDriver $smsChannelDriver,
        EmailChannelDriver $emailChannelDriver,
    ) {
        $this->drivers = [
            'sms' => $smsChannelDriver,
            'email' => $emailChannelDriver,
        ];
    }

    public function queue(array $attributes): Notification
    {
        $payload = Arr::get($attributes, 'payload', []);
        $templateId = Arr::get($attributes, 'notification_template_id');
        $memberId = Arr::get($attributes, 'member_id');

        $notificationAttributes = Arr::only($attributes, [
            'tenant_id',
            'notification_template_id',
            'member_id',
            'channel',
            'recipient',
            'subject',
            'body',
            'payload',
            'scheduled_for',
        ]);

        $template = null;
        if ($templateId) {
            $template = NotificationTemplate::query()
                ->where('tenant_id', $attributes['tenant_id'] ?? null)
                ->findOrFail($templateId);

            $rendered = $this->applyTemplate($template, $payload ?? []);
            $notificationAttributes['body'] = $notificationAttributes['body'] ?? $rendered['body'];
            if ($rendered['subject']) {
                $notificationAttributes['subject'] = $notificationAttributes['subject'] ?? $rendered['subject'];
            }
            $notificationAttributes['channel'] = $template->channel;
        }

        if ($memberId) {
            $member = Member::query()
                ->with('contacts')
                ->where('tenant_id', $attributes['tenant_id'] ?? null)
                ->findOrFail($memberId);

            $notificationAttributes['member_id'] = $member->id;
            $notificationAttributes['recipient'] = $notificationAttributes['recipient']
                ?? $this->resolveRecipientFromMember($member, $notificationAttributes['channel'] ?? $template?->channel ?? 'sms');
        }

        $notificationAttributes['payload'] = $payload ?: null;
        $notification = Notification::create($notificationAttributes);

        if ($notification->scheduled_for instanceof Carbon && $notification->scheduled_for->isFuture()) {
            SendNotificationJob::dispatch($notification->id)->delay($notification->scheduled_for);
        } else {
            SendNotificationJob::dispatch($notification->id);
        }

        return $notification->fresh();
    }

    public function send(Notification $notification): NotificationChannelResponse
    {
        if (! $notification->isDue()) {
            return NotificationChannelResponse::failure('scheduler', 'Notification is not due yet.');
        }

        $notification->markSending();

        $driver = $this->resolveDriver($notification->channel);

        if (! $driver) {
            Log::warning('No driver registered for channel', ['channel' => $notification->channel]);
            $notification->markFailed('Missing channel driver.');

            return NotificationChannelResponse::failure('system', 'Missing channel driver.');
        }

        $response = $driver->send($notification);

        if ($response->successful) {
            $notification->markSent($response->provider ?? $notification->provider ?? 'unknown', $response->providerMessageId);
        } else {
            $notification->markFailed($response->errorMessage ?? 'Unknown error.');
        }

        return $response;
    }

    private function resolveDriver(string $channel): ?NotificationChannelDriver
    {
        return $this->drivers[$channel] ?? null;
    }

    private function applyTemplate(NotificationTemplate $template, array $payload): array
    {
        $replacements = [];
        foreach ($payload as $key => $value) {
            if (is_scalar($value)) {
                $replacements['{{' . $key . '}}'] = (string) $value;
            }
        }

        $body = strtr($template->body, $replacements);
        $subject = $template->subject ? strtr($template->subject, $replacements) : null;

        return compact('body', 'subject');
    }

    private function resolveRecipientFromMember(Member $member, string $channel): string
    {
        $contact = $member->preferredContact();

        if ($contact && $contact->type === 'email' && $channel === 'email') {
            return $contact->value;
        }

        if ($contact && $contact->type === 'mobile' && $channel === 'sms') {
            return $contact->value;
        }

        if ($channel === 'email') {
            $email = $member->contacts->firstWhere('type', 'email');
            if ($email) {
                return $email->value;
            }
        }

        if ($channel === 'sms') {
            $phone = $member->contacts->firstWhere(fn ($contact) => in_array($contact->type, ['mobile', 'home_phone'], true));
            if ($phone) {
                return $phone->value;
            }
        }

        return $channel === 'email' ? 'unknown@example.com' : '+0000000000';
    }
}
