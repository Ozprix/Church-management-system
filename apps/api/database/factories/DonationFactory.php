<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Donation;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Donation>
 */
class DonationFactory extends Factory
{
    protected $model = Donation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'member_id' => Member::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'amount' => $this->faker->randomFloat(2, 20, 500),
            'currency' => 'USD',
            'status' => 'succeeded',
            'received_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'provider' => 'stripe',
            'provider_reference' => $this->faker->uuid(),
            'receipt_number' => strtoupper($this->faker->bothify('RCPT-####')),
            'notes' => $this->faker->optional()->sentence(),
            'metadata' => null,
        ];
    }
}
