<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\RecurringDonationSchedule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringDonationSchedule>
 */
class RecurringDonationScheduleFactory extends Factory
{
    protected $model = RecurringDonationSchedule::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 month', '+1 month');

        return [
            'tenant_id' => Tenant::factory(),
            'member_id' => Member::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'frequency' => $this->faker->randomElement(['weekly', 'monthly', 'quarterly']),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'currency' => 'USD',
            'status' => 'active',
            'starts_on' => $start,
            'ends_on' => null,
            'next_run_at' => $start,
            'metadata' => null,
        ];
    }
}
