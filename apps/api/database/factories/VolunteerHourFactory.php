<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\VolunteerHour;
use Illuminate\Database\Eloquent\Factories\Factory;

class VolunteerHourFactory extends Factory
{
    protected $model = VolunteerHour::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'volunteer_assignment_id' => null,
            'member_id' => null,
            'served_on' => $this->faker->date(),
            'hours' => $this->faker->randomFloat(2, 0.5, 8),
            'source' => 'manual',
            'notes' => null,
            'metadata' => null,
        ];
    }
}
