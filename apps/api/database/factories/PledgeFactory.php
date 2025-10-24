<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Fund;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pledge>
 */
class PledgeFactory extends Factory
{
    protected $model = Pledge::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 month', '+1 month');

        return [
            'tenant_id' => Tenant::factory(),
            'member_id' => Member::factory(),
            'fund_id' => Fund::factory(),
            'amount' => $this->faker->randomFloat(2, 50, 1000),
            'fulfilled_amount' => 0,
            'currency' => 'USD',
            'frequency' => $this->faker->randomElement(['monthly', 'weekly', 'annually']),
            'start_date' => $start,
            'end_date' => null,
            'status' => 'active',
            'notes' => $this->faker->optional()->sentence(),
            'metadata' => null,
            'reminder_enabled' => false,
            'reminder_cadence' => null,
            'next_reminder_at' => null,
            'last_reminder_sent_at' => null,
        ];
    }

    public function withReminders(string $cadence = 'monthly', ?string $nextReminder = null): self
    {
        return $this->state(function () use ($cadence, $nextReminder): array {
            return [
                'reminder_enabled' => true,
                'reminder_cadence' => $cadence,
                'next_reminder_at' => $nextReminder ?? now()->addWeek(),
            ];
        });
    }
}
