<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseLineItem>
 */
class ExpenseLineItemFactory extends Factory
{
    protected $model = ExpenseLineItem::class;

    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'description' => $this->faker->sentence(6),
            'amount' => $this->faker->randomFloat(2, 5, 200),
            'category' => $this->faker->randomElement(['operations', 'benevolence', 'outreach', null]),
            'metadata' => null,
        ];
    }
}
