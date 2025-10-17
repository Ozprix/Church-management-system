<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Donation;
use App\Models\RecurringDonationAttempt;
use App\Models\RecurringDonationSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringDonationAttempt>
 */
class RecurringDonationAttemptFactory extends Factory
{
    protected $model = RecurringDonationAttempt::class;

    public function definition(): array
    {
        return [
            'schedule_id' => RecurringDonationSchedule::factory(),
            'donation_id' => Donation::factory(),
            'status' => 'pending',
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'currency' => 'USD',
            'provider' => 'stripe',
            'provider_reference' => $this->faker->uuid(),
            'processed_at' => null,
            'metadata' => null,
        ];
    }
}
