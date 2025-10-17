<?php

namespace App\Services;

use App\Jobs\SendNotificationJob;
use App\Models\Member;
use App\Models\NotificationRule;
use App\Models\NotificationRuleRun;
use App\Services\NotificationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationAutomationService
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function createRule(array $attributes): NotificationRule
    {
        $rule = NotificationRule::create($attributes);

        return $rule->fresh();
    }

    public function updateRule(NotificationRule $rule, array $attributes): NotificationRule
    {
        $rule->fill($attributes);
        $rule->save();

        return $rule->fresh();
    }

    public function runRule(NotificationRule $rule): NotificationRuleRun
    {
        return DB::transaction(function () use ($rule): NotificationRuleRun {
            $run = NotificationRuleRun::create([
                'notification_rule_id' => $rule->id,
                'ran_at' => Carbon::now(),
                'status' => 'processing',
            ]);

            try {
                $recipients = $this->resolveRecipients($rule);
                $run->matched_count = $recipients->count();

                $sent = 0;
                foreach ($recipients as $member) {
                    $payload = [
                        'tenant_id' => $rule->tenant_id,
                        'member_id' => $member?->id,
                        'notification_template_id' => $rule->notification_template_id,
                        'channel' => $rule->channel,
                        'payload' => [
                            'member' => $member?->only(['first_name', 'last_name', 'preferred_name']) ?? null,
                            'rule' => $rule->only(['id', 'name', 'slug']),
                        ],
                    ];

                    $notification = $this->notificationService->queue($payload);
                    SendNotificationJob::dispatch($notification->id);
                    $sent++;
                }

                $run->sent_count = $sent;
                $run->status = 'completed';
                $run->save();
            } catch (\Throwable $throwable) {
                $run->status = 'failed';
                $run->error_message = $throwable->getMessage();
                $run->save();
            }

            return $run->fresh();
        });
    }

    protected function resolveRecipients(NotificationRule $rule)
    {
        $query = Member::query()->where('tenant_id', $rule->tenant_id);

        $config = $rule->trigger_config ?? [];

        if ($status = Arr::get($config, 'member_status')) {
            $query->where('membership_status', $status);
        }

        if ($stage = Arr::get($config, 'membership_stage')) {
            $query->where('membership_stage', $stage);
        }

        $limit = Arr::get($config, 'limit', 100);

        return $query->limit($limit)->get();
    }
}
