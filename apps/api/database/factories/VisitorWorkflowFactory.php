<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\VisitorWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitorWorkflow>
 */
class VisitorWorkflowFactory extends Factory
{
    protected $model = VisitorWorkflow::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'is_active' => true,
        ];
    }
}
