<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $channel = $this->faker->randomElement(['sms', 'email']);

        return [
            'tenant_id' => Tenant::factory(),
            'notification_template_id' => null,
            'member_id' => null,
            'channel' => $channel,
            'recipient' => $channel === 'sms' ? $this->faker->e164PhoneNumber() : $this->faker->safeEmail(),
            'subject' => $channel === 'email' ? $this->faker->sentence() : null,
            'body' => $this->faker->paragraph(),
            'payload' => ['foo' => 'bar'],
            'status' => 'queued',
            'scheduled_for' => null,
            'sent_at' => null,
            'attempts' => 0,
        ];
    }

    public function forTemplate(NotificationTemplate $template): self
    {
        return $this->state(fn () => [
            'tenant_id' => $template->tenant_id,
            'notification_template_id' => $template->id,
            'channel' => $template->channel,
        ]);
    }

    public function forMember(Member $member): self
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $member->tenant_id,
            'member_id' => $member->id,
            'recipient' => $attributes['channel'] === 'sms'
                ? ($member->preferredContact()?->value ?? $this->faker->e164PhoneNumber())
                : $member->preferredContact()?->value,
        ] + $attributes);
    }

    public function sent(): self
    {
        return $this->state(fn () => [
            'status' => 'sent',
            'sent_at' => now(),
            'provider' => 'test',
            'provider_message_id' => $this->faker->uuid(),
        ]);
    }
}
