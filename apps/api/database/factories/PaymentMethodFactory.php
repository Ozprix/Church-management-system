<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'member_id' => Member::factory(),
            'type' => 'card',
            'brand' => 'Visa',
            'last_four' => (string) $this->faker->numberBetween(1000, 9999),
            'provider' => 'stripe',
            'provider_reference' => $this->faker->uuid(),
            'expires_at' => now()->addYear(),
            'is_default' => true,
            'metadata' => null,
        ];
    }
}
