<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Donation;
use App\Models\FinancialLedgerEntry;
use App\Models\Pledge;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialLedgerEntry>
 */
class FinancialLedgerEntryFactory extends Factory
{
    protected $model = FinancialLedgerEntry::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'donation_id' => Donation::factory(),
            'pledge_id' => null,
            'entry_type' => 'credit',
            'account' => 'Donations Income',
            'amount' => $this->faker->randomFloat(2, 20, 500),
            'currency' => 'USD',
            'occurred_at' => now(),
            'description' => $this->faker->optional()->sentence(),
            'metadata' => null,
        ];
    }
}
