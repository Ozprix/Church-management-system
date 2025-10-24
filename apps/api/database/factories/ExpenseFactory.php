<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        $incurred = Carbon::now()->subDays($this->faker->numberBetween(0, 30));

        return [
            'tenant_id' => Tenant::factory(),
            'submitted_by' => User::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'total_amount' => $this->faker->randomFloat(2, 20, 500),
            'currency' => 'USD',
            'status' => Expense::STATUS_DRAFT,
            'incurred_at' => $incurred->toDateString(),
            'metadata' => null,
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (): array => [
            'status' => Expense::STATUS_PENDING,
            'submitted_at' => Carbon::now()->subDay(),
        ]);
    }

    public function approved(): self
    {
        return $this->state(fn (): array => [
            'status' => Expense::STATUS_APPROVED,
            'submitted_at' => Carbon::now()->subDays(2),
            'approved_at' => Carbon::now()->subDay(),
            'approved_by' => User::factory(),
        ]);
    }

    public function reimbursed(): self
    {
        return $this->state(fn (): array => [
            'status' => Expense::STATUS_REIMBURSED,
            'submitted_at' => Carbon::now()->subDays(5),
            'approved_at' => Carbon::now()->subDays(4),
            'reimbursed_at' => Carbon::now()->subDay(),
            'approved_by' => User::factory(),
            'reimbursed_by' => User::factory(),
        ]);
    }
}
