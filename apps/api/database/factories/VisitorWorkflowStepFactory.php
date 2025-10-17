<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VisitorWorkflow;
use App\Models\VisitorWorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitorWorkflowStep>
 */
class VisitorWorkflowStepFactory extends Factory
{
    protected $model = VisitorWorkflowStep::class;

    public function definition(): array
    {
        return [
            'workflow_id' => VisitorWorkflow::factory(),
            'step_number' => $this->faker->unique()->numberBetween(1, 5),
            'name' => $this->faker->sentence(3),
            'delay_minutes' => $this->faker->numberBetween(0, 720),
            'channel' => $this->faker->randomElement(['email', 'sms']),
            'notification_template_id' => null,
            'metadata' => null,
            'is_active' => true,
        ];
    }
}
