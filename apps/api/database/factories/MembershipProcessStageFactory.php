<?php

namespace Database\Factories;

use App\Models\MembershipProcess;
use App\Models\MembershipProcessStage;
use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipProcessStageFactory extends Factory
{
    protected $model = MembershipProcessStage::class;

    public function definition(): array
    {
        return [
            'process_id' => MembershipProcess::factory(),
            'key' => $this->faker->unique()->slug(),
            'name' => $this->faker->words(2, true),
            'step_order' => 1,
            'entry_actions' => null,
            'exit_actions' => null,
            'reminder_minutes' => null,
            'reminder_template_id' => null,
            'metadata' => null,
        ];
    }
}
