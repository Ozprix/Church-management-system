<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VisitorFollowup;
use App\Models\VisitorFollowupLog;
use App\Models\VisitorWorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitorFollowupLog>
 */
class VisitorFollowupLogFactory extends Factory
{
    protected $model = VisitorFollowupLog::class;

    public function definition(): array
    {
        return [
            'followup_id' => VisitorFollowup::factory(),
            'step_id' => VisitorWorkflowStep::factory(),
            'status' => $this->faker->randomElement(['queued', 'sent', 'skipped']),
            'channel' => $this->faker->randomElement(['email', 'sms', 'task']),
            'run_at' => now(),
            'notes' => $this->faker->optional()->sentence(),
            'metadata' => null,
        ];
    }
}
