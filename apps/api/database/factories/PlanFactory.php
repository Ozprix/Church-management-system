<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'code' => 'plan-' . $this->faker->unique()->lexify('????'),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'monthly_price' => $this->faker->randomFloat(2, 10, 200),
            'annual_price' => $this->faker->randomFloat(2, 100, 2000),
            'currency' => 'USD',
            'is_active' => true,
            'limits' => [
                'members' => 500,
                'volunteer_signups' => 100,
            ],
        ];
    }
}
