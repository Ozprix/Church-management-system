<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFeatureFactory extends Factory
{
    protected $model = PlanFeature::class;

    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'feature' => $this->faker->unique()->slug(),
            'limit' => $this->faker->numberBetween(10, 1000),
            'metadata' => ['seeded' => true],
        ];
    }
}
