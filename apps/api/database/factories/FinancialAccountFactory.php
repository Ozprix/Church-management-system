<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinancialAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FinancialAccount>
 */
class FinancialAccountFactory extends Factory
{
    protected $model = FinancialAccount::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['asset', 'liability', 'equity', 'income', 'expense']);
        $codePrefix = [
            'asset' => 'assets',
            'liability' => 'liabilities',
            'equity' => 'equity',
            'income' => 'income',
            'expense' => 'expense',
        ][$type];

        return [
            'tenant_id' => Tenant::factory(),
            'code' => $codePrefix . '.' . Str::slug($this->faker->unique()->words(2, true)),
            'name' => Str::title($this->faker->words(3, true)),
            'type' => $type,
            'is_system' => false,
            'is_active' => true,
            'metadata' => null,
        ];
    }
}
