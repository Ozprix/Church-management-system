<?php

namespace Database\Factories;

use App\Models\NotificationRule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationRuleFactory extends Factory
{
    protected $model = NotificationRule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->unique()->slug(),
            'trigger_type' => 'membership_status',
            'trigger_config' => ['member_status' => 'active'],
            'channel' => 'email',
            'notification_template_id' => null,
            'delivery_config' => null,
            'status' => 'inactive',
            'throttle_minutes' => 0,
            'metadata' => null,
        ];
    }
}
